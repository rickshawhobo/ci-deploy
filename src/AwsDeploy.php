<?php
namespace HRS\Ci;

class AwsDeploy
{

    private function pushToEcr($ecrTag)
    {

        $cmd = "aws ecr get-login --no-include-email";

        $output = exec($cmd);
        passthru($output);

        $cmd = "docker push $ecrTag";
        passthru($cmd);
    }

    private function getTaskDefinition($task)
    {

        exec('aws ecs describe-task-definition --task-definition ' . $task, $output, $retVal);
        if ($retVal !== 0) {
            echo "Empty task definition $task";
            exit(1);
        }
        $outputString = implode($output);
        return json_decode($outputString, true);
    }

    private function updateTaskDefinition($task, $data)
    {

        $fileData = [];
        $fileData['containerDefinitions'] = $data['taskDefinition']['containerDefinitions'];
        $fileData['volumes'] = $data['taskDefinition']['volumes'];
        

        $fileName = '/tmp/' . $task . "-tmp.json";

        $fileData = json_encode($fileData);

        file_put_contents($fileName, $fileData);

        $revision = shell_exec("aws ecs register-task-definition --family $task --cli-input-json file://$fileName");
        $revisionData = json_decode($revision, true);
        if (isset($revisionData['taskDefinition']['taskDefinitionArn'])) {
            $newTd = $revisionData['taskDefinition']['taskDefinitionArn'];
            echo "updated new td $newTd";
            return $newTd;
        } else {
            exit(1);
        }
    }

    private function updateService($cluster, $service, $task)
    {

        exec("aws ecs update-service --cluster $cluster --service $service --task-definition $task", $output, $retVal);

        if ($retVal !== 0) {
            echo "Deploy to $cluster failed";
        }
        $outputString = implode($output);
        $resultData = json_decode($outputString, true);

        if (isset($resultData['service']['taskDefinition'])) {
            echo "deployed to $cluster";
            return true;
        } else {
            exit(1);
        }
    }

    public function deploy($cluster, $service, $task, $ecrTag)
    {


        $localTag = Builder::getLocalImageTag();

        $cmd = "docker tag $localTag $ecrTag";
        echo $cmd;
        passthru($cmd);

        $this->pushToEcr($ecrTag);

        // multi services support
        if (is_array($service) && isset($service['name']) && isset($service['services'])) {
            foreach ($service['services'] as $srvName) {
                $tenantTask = $task . '-' . $srvName;
                $srvName = $service['name'] . '-' . $srvName;
                $this->refreshService($cluster, $srvName, $tenantTask, $ecrTag);
            }
        } else {
            $this->refreshService($cluster, $service, $task, $ecrTag);
        }
    }

    private function refreshService($cluster, $service, $task, $ecrTag)
    {
        $data = $this->getTaskDefinition($task);

        $data['taskDefinition']['containerDefinitions'][0]['image'] = $ecrTag;

        $this->updateTaskDefinition($task, $data);

        $this->updateService($cluster, $service, $task);
    }

    private function getEcrTag($account, $image)
    {
        return "{$account}.dkr.ecr.us-east-1.amazonaws.com/{$image}";
    }

    public function composerDeploy($env, $cluster, $service, $task, $image, $account)
    {

        $container = Builder::getContainerName();
        $ecrTag = $this->getEcrTag($account, $image);


        if ($env == 'live') {
            // have to rebuild the container because it contains live credentials
            $builder = new ComposerBuilder();
            $builder->build('live');
        }
        $localTag = Builder::getLocalImageTag();

        $cmd = "docker container rm $container || exit 0";
        passthru($cmd);

        $refName = getenv('CI_COMMIT_REF_NAME');
        passthru("echo $refName > version.txt");

        $versionPath = getenv('VERSION_PATH') ?? '/var/www/project/web/';

        $cmd = "docker run --rm -d -e COMPOSER_INSTALL=0 --name $container $localTag \
            && docker cp version.txt $container:{$versionPath}version.txt \
            && docker exec $container composer install \
            && docker commit $container $localTag";

        echo $cmd;
        passthru($cmd, $retVal);

        if ($retVal !== 0) {
            echo "Could not deploy";
            exit(1);
        }

        $this->deploy($cluster, $service, $task, $ecrTag);
    }
    public function genericDeploy($env, $cluster, $service, $task, $image, $account)
    {
        $container = Builder::getContainerName();
        $ecrTag = $this->getEcrTag($account, $image);

        $cmd = "docker container rm $container || exit 0";
        passthru($cmd);

        $refName = getenv('CI_COMMIT_REF_NAME');
        passthru("echo $refName > version.txt");

        $localTag = Builder::getLocalImageTag();

        $versionPath = getenv('VERSION_PATH') ?? '/var/www/html/api/';

        $cmd = "docker run --rm -d --name $container $localTag \
            && docker cp version.txt $container:{$versionPath}version.txt \
            && docker commit $container $localTag";

        echo $cmd;
        passthru($cmd, $retVal);

        if ($retVal !== 0) {
            echo "Could not deploy";
            exit(1);
        }
        $this->deploy($cluster, $service, $task, $ecrTag);
    }

    public function mvnDeploy($env, $cluster, $service, $task, $image, $account)
    {

        $ecrTag = $this->getEcrTag($account, $image);
        $this->deploy($cluster, $service, $task, $ecrTag);
    }

    public function cfDeploy($env, $s3, $cloudFrontId, $path = "./build")
    {

        // rebuild the code to point to live
        $builder = new GulpBuilder();
        $builder->build($env, false);

        $refName = getenv('CI_COMMIT_REF_NAME');
        passthru("echo $refName > build/version.txt");
        $cmd = "aws s3 cp {$path} s3://{$s3}/ --recursive --include \"*\" --acl public-read --cache-control public,max-age=31536000,no-transform";



        passthru($cmd, $retVal);

        # invalidate the whole distribution
        $cmd = "aws configure set preview.cloudfront true";
        passthru($cmd, $retVal);

        $cmd = "aws cloudfront create-invalidation --distribution-id {$cloudFrontId} --paths '/*'";
        passthru($cmd, $retVal);
    }
}
