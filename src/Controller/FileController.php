<?php
namespace App\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Routing\Annotation\Route;
/**
 * Movie controller.
 * @Route("/api", name="api_")
 */
class FileController extends AbstractFOSRestController
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * @var array
     */
    private $final = [];

    /**
     * @var array
     */
    private $queue = [];

    /**
     * Create Movie.
     * @Rest\Post("/file_processor")
     *
     * @return Response
     */
    public function postFile(Request $request)
    {
        $requestData = json_decode($request->getContent(), true);
        $this->data = $requestData['tasks'];
        foreach ($this->data as $obj) {
            try {
                list ($this->final, $this->queue) = $this->getRecurNextNode($obj, $this->data, $this->final, $this->queue);
            } catch (\Exception $e) {
                die($e->getMessage());
            }
        }

        // Print out result
        $str = '';
        if(count($this->final)>0){
            foreach ($this->final as $item) {
                try {
                $str = $str.$item['command']."\n";
                } catch (\Exception $e) {
                    die($e->getMessage());
                }
            }
        }
        try {
            $myfile = fopen($requestData['filename'], "w") or die("Unable to open file!");
            fwrite($myfile, $str);
            fclose($myfile);
        }catch (\Exception $e) {
                die($e->getMessage());
            }

        return $this->handleView($this->view('success'));
    }

    /**
     * @param $object
     * @param array $data
     * @param array $final
     * @param array $queue
     * @return array
     */
    private function getRecurNextNode($object, array $data, array $final, array $queue) {
        array_push($queue, $this->formatOutput($object));
        if(isset($object['dependencies'])) {
            sort($object['dependencies']);
            foreach ($object['dependencies'] as $dep) {
                if (!$this->inArrayMulti($final,'name',$dep)) {
                    if (!$this->inArrayMulti($queue,'name',$dep)) {
                        $depArr = $this->getDependency($data, $dep);
                        if($depArr){
                            array_push($queue, $this->formatOutput($depArr));
                            list($final, $queue) = $this->getRecurNextNode($depArr, $data, $final, $queue);

                        }
                    } else {
                        throw new \RuntimeException("Circular dependency: " . $object['name'] . " -> $dep");
                    }
                }
            }
        }
        // Add to final printable array
        if (!$this->inArrayMulti($final,'name',$object['name'])) {
            array_push($final,$this->formatOutput($object));

        }
        // Remove all occurrences from queue
        while (($index = $this->getDependency($queue, $object['name'], 'key')) !== false) {
            unset($queue[$index]);
        }

        return [$final, $queue];
    }

    /**
     * @param $arr
     * @return array
     */
    private function formatOutput($arr){
        $output = array();
        if(isset($arr['name']))$output['name'] = $arr['name'];
        if(isset($arr['command']))$output['command'] = $arr['command'];
        return $output;

    }

    /**
     * @param $data
     * @param $searchKey
     * @param $searchValue
     * @return bool
     */
    private function inArrayMulti($data,$searchKey,$searchValue){
        if(count($data)>0){
            foreach ($data as $item){
                if($item[$searchKey] == $searchValue) return true;
            }
        }

        return false;
    }

    /**
     * @param $data
     * @param $dependencyName
     * @param string $type
     * @return false|int|mixed|string|null
     */
    private function getDependency($data, $dependencyName,$type='value'){
        $depArr = array_filter($data, function($d) use($dependencyName){
            return ($d['name'] == $dependencyName);
        });
        if($type=='key'){
            if(count($depArr)>0) return array_key_first($depArr);
            else return false;
        }else{
            if(count($depArr)>0) return current($depArr);
            else return false;
        }

    }
}