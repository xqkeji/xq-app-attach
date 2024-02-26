<?php
/*
 * xqkeji.cn
 * @copyright 2021 新齐科技 (http://www.xqkeji.cn/)
 * @author 张文豪  <support@xqkeji.cn>
 */
namespace xqkeji\app\attach\controller;

use xqkeji\mvc\Controller;
use xqkeji\mvc\builder\Model;

class Image extends Controller
{
	public $thumbConfig=[
		'list_thumb'=>['width'=>193,'height'=>null,'master'=>\xqkeji\image\Enum::WIDTH],
		'carousel_thumb'=>['width'=>450,'height'=>null,'master'=>\xqkeji\image\Enum::WIDTH],
		'ad_thumb'=>['width'=>null,'height'=>116,'master'=>\xqkeji\image\Enum::HEIGHT],
	];
	public $allowedFileExtensions=["jpg","jpeg","gif","png"];
	public $maxSize=1000;//以KB为单位
	public $maxWidth=0;//限制图片最大宽度
	public $maxHeight=0;//限制图片最大高度
	public $isThumb=0;//是否生成缩略图
	public $thumbWidth=200;//缩略图宽度
	public $thumbHeight=200;//缩略图高度
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
		   $thumbConfig=false;
		   if(isset($_GET['thumb']))
		   {
		   		$thumb=htmlspecialchars($_GET['thumb']);
		   		if(isset($this->thumbConfig[$thumb]))
		   		{
		   			$thumbConfig=$this->thumbConfig[$thumb];
		   		}
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
			// 限制上传图片的宽或高 (注意：0 表示无限制)
			if($this->maxWidth > 0 || $this->maxHeight>0)
			{
				list ($w, $h) = @getimagesize($file->getTempName());
				if($this->maxWidth > 0)
				{
					if ($w > $this->maxWidth) {
						$result=['code'=>10004,'error'=>'图片超出最大的宽度'];
						$this->returnJSON($result);
					}
				}
				if($this->maxHeight > 0)
				{
					if ($h > $this->maxHeight) {
						$result=['code'=>10005,'error'=>'图片超出最大的高度'];
						$this->returnJSON($result);
					}
				}
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
				
				if(\xqkeji\image\adapter\Gd::check())
				{
					if($thumbConfig!==false)
					{
						$thumb_file=$des_path.$ds.$file_name.'_thumb.'.$file->getExtension();
						$image=new \xqkeji\image\adapter\Gd($upload_path.$dest_file);
						$image->resize ( $thumbConfig['width'], $thumbConfig['height'],$thumbConfig['master'] );
						if ($image->save ($upload_path.$thumb_file)) 
						{
							$image_url=$upload_url.str_replace('\\','/',$dest_file);
							$thumb_url=$upload_url.str_replace('\\','/',$thumb_file);
							$image_filename=$upload_path.$dest_file;
							$thumb_filename=$upload_path.$thumb_file;
							
						} else {
							$result=['code'=>10008,'message'=>'生成缩略图失败'];
							$this->returnJSON($result);
						}
					}
					else
					{
						$image_url=$upload_url.str_replace('\\','/',$dest_file);
						$thumb_url='';
						$image_filename=$upload_path.$dest_file;
						$thumb_filename='';
					}
				}
				else
				{
					$result=['code'=>10007,'error'=>'PHP扩展GD没有安装'];
					$this->returnJSON($result);
				}
				
			}

			$model=Model::getModel();
			$row=[
				'image_url'=>$image_url,
				'thumb_url'=>$thumb_url,
				'type'=>'image',
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
					$image_url
				],
				'initialPreviewAsData'=>true,
				'initialPreviewConfig'=>[
					 [
                        'type' => 'image',  
                        'caption' => htmlspecialchars($_POST['fileId']),
                        'key' => $key,       // keys for deleting/reorganizing preview
                        'size' => $file->getSize(),    // file size
						'zoomData'=>$image_url,
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
		$image_url=$row->getAttr('image_url');
		$image_path=str_replace(['/'], $ds, $image_url);
        $filePath=$www_path.$image_path;
        if(is_file($filePath))
        {
        	unlink($filePath);
        	$result=['key'=>$key,'message'=>'删除图片成功'];
        }
        else
        {
        	$result=['key'=>$key,'error'=>'文件不存在'];
        }
		
		$this->returnJSON($result);

	}
    
}
