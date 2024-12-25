<?php
/*
 * xqkeji.cn
 * @copyright 2021 新齐科技 (http://www.xqkeji.cn/)
 * @author 张文豪  <support@xqkeji.cn>
 */
namespace xqkeji\app\attach\controller;

use xqkeji\mvc\Controller;
use xqkeji\mvc\builder\Model;

class Doc extends Controller
{
	public $allowedFileExtensions=["doc","docx","pdf","wps","txt","zip","rar","tar","gz"];
	public $maxSize=2000;//以KB为单位
	public $maxFileCount=1;//上传最多的文件数
	
    public function upload()
    {
    	$this->view->disable();
		$ds=XQ_DS;
       	if ($this->request->hasFiles()) {
		   $files=$this->request->getUploadedFiles();

		   if($this->maxFileCount>0 && count($files)>$this->maxFileCount)
		   {
				$result=['code'=>10001,'error'=>'超出最大文件数量'];
					
				$this->returnJSON($result);
		   }
		   
		   $file=current($files);
		   
		   $module_name=htmlspecialchars($_POST['moduleName']);
		   $image_filename='';
		   $image_url='';
		   $thumb_filename='';
		   $thumb_url='';
			if ($this->maxSize > 0 && ($file->getSize() / 1024 > $this->maxSize))  // 限制上传文件的大小，文件大小以字节计算，0表示不限！
			{
				$result=['code'=>10002,'error'=>'超出最大文件大小'];
				
				$this->returnJSON($result);
			}
			
			if(!in_array(strtolower($file->getExtension()),$this->allowedFileExtensions))
			{
				$result=['code'=>10003,'error'=>'非法文件类型'];
				
				$this->returnJSON($result);
			}
			
		
			$upload_path=call_user_func(['xqkeji\\App','getUploadPath']);
			$upload_path_name=call_user_func(['xqkeji\\App','getUploadPathName']);
			$upload_url='/'.$upload_path_name.'/';
			if(!file_exists($upload_path))
			{
				mkdir($upload_path,0777);
			}
			$des_path=$module_name;
			if (!file_exists($upload_path.$des_path.$ds))
			{
				mkdir($upload_path.$des_path.$ds,0777);
			}
			$des_path=$des_path.$ds.date('Ym');
			if (!file_exists($upload_path.$des_path.$ds))
			{
				mkdir($upload_path.$des_path.$ds,0777);
			}		
			$file_name = date("YmdHis") . '_' . uniqid();
			$dest_file =$des_path.$ds.$file_name.'.'.$file->getExtension();
			if(!$file->moveTo($upload_path.$dest_file))
			{
				$result=['code'=>10006,'error'=>'文件上传失败'];
				$this->returnJSON($result);
			}
			else
			{
				$file_url=$upload_url.str_replace('\\','/',$dest_file);
				
			}

			$model=Model::getModel();
			$row=[
				'file_url'=>$file_url,
				'type'=>strtolower($file->getExtension()),
				'caption'=>htmlspecialchars($_POST['fileId']),
				'size'=>$file->getSize(),
				'auth_type'=>$this->auth->getCurrentAuthType(),
				'auth_id'=>$this->auth->getCurrentAuthId(),
				'module_name'=>htmlspecialchars($_POST['moduleName']),
				'controller_name'=>htmlspecialchars($_POST['controllerName']),
				'action_name'=>htmlspecialchars($_POST['actionName']),
				'action_params'=>htmlspecialchars($_POST['actionParams']),
				'source_id'=>'',
			];
			$model->save($row);
			$key=(string)$model->getKey();
		    $data=[
				'initialPreview'=>[
					$file_url
				],
				'initialPreviewAsData'=>true,
				'initialPreviewConfig'=>[
					 [
                        'type' => 'other',  
                        'caption' => htmlspecialchars($_POST['fileId']),
                        'key' => $key,       // keys for deleting/reorganizing preview
                        'size' => $file->getSize(),    // file size
						'zoomData'=>$file_url,
                    ]
				],
				'append' => true,
			];
		    

			$this->returnJSON($data);
			
        }
		else
		{
			$result=['code'=>10000,'error'=>'没有文件上传'];
			$this->returnJSON($result);
		}
		
    }
	public function delete()
	{
		$this->view->disable();
		$ds=XQ_DS;
		$www_path=call_user_func(['xqkeji\\App','getWwwPath']);
		$key=htmlspecialchars($_POST['key']);
		$model=Model::getModel();
		$row=$model->find($key);
		if(!empty($row))
		{
			$doc_url=$row->getAttr('file_url');
			$doc_path=str_replace(['/'], $ds, $doc_url);
			$filePath=$www_path.$doc_path;
			if(is_file($filePath))
			{
				unlink($filePath);
				$result=['key'=>$key,'message'=>'删除文档成功'];
			}
			else
			{
				$result=['key'=>$key,'error'=>'文件不存在'];
			}
		}
		else
		{
			$result=['key'=>'','error'=>'查询不到文档'];
		}
		
		
		$this->returnJSON($result);

	}
    
}
