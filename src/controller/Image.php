<?php
/*
 * xqkeji.cn
 * @copyright 2021 新齐科技 (http://www.xqkeji.cn/)
 * @author 张文豪  <support@xqkeji.cn>
 */
namespace xqkeji\app\attach\controller;

use xqkeji\mvc\Controller;

class Image extends Controller
{
	use FileUpload;

	//缩略图规格配置，前端通过 ?thumb=键名 选择
	public $thumbConfig=[
		'list_thumb'=>['width'=>193,'height'=>null,'master'=>\xqkeji\image\Enum::WIDTH],
		'carousel_thumb'=>['width'=>450,'height'=>null,'master'=>\xqkeji\image\Enum::WIDTH],
		'ad_thumb'=>['width'=>null,'height'=>116,'master'=>\xqkeji\image\Enum::HEIGHT],
	];
	public $allowedFileExtensions=["jpg","jpeg","gif","png"];
	public $maxSize=1000;//以KB为单位
	public $maxWidth=0;//限制图片最大宽度
	public $maxHeight=0;//限制图片最大高度
	public $maxFileCount=1;//上传最多的文件数
	public $fileType='image';
	public $fileUrlField='image_url';
	public $thumbUrlField='thumb_url';
	public $deleteMessage='删除图片成功';

	// 落盘前校验图片宽高，并提前确认GD扩展可用
	protected function beforeSave($file)
	{
		if(!\xqkeji\image\adapter\Gd::check())
		{
			$this->errorJson(['code'=>10007,'error'=>'PHP扩展GD没有安装']);
			return null;
		}
		// 限制上传图片的宽或高 (注意：0 表示无限制)
		if($this->maxWidth > 0 || $this->maxHeight>0)
		{
			list ($w, $h) = @getimagesize($file->getTempName());
			if($this->maxWidth > 0 && $w > $this->maxWidth)
			{
				$this->errorJson(['code'=>10004,'error'=>'图片超出最大的宽度']);
				return null;
			}
			if($this->maxHeight > 0 && $h > $this->maxHeight)
			{
				$this->errorJson(['code'=>10005,'error'=>'图片超出最大的高度']);
				return null;
			}
		}
		return true;
	}

	protected function buildResult($file, $desPath, $destFile, $uploadUrl)
	{
		$thumbConfig=false;
		if(isset($_GET['thumb']))
		{
			$thumb=htmlspecialchars($_GET['thumb']);
			if(isset($this->thumbConfig[$thumb]))
			{
				$thumbConfig=$this->thumbConfig[$thumb];
			}
		}
		$image_url=$uploadUrl.str_replace('\\','/',$destFile);
		$thumb_url='';
		if($thumbConfig!==false)
		{
			$ds=XQ_DS;
			$upload_path=call_user_func(['xqkeji\\App','getUploadPath']);
			$thumb_file=$desPath.$ds.substr($destFile,strlen($desPath.$ds),-strlen('.'.$file->getExtension())).'_thumb.'.$file->getExtension();
			$image=new \xqkeji\image\adapter\Gd($upload_path.$destFile);
			$image->resize($thumbConfig['width'],$thumbConfig['height'],$thumbConfig['master']);
			if(!$image->save($upload_path.$thumb_file))
			{
				$this->errorJson(['code'=>10008,'error'=>'生成缩略图失败']);
				return;
			}
			$thumb_url=$uploadUrl.str_replace('\\','/',$thumb_file);
		}
		$key=$this->saveRow([
			'image_url'=>$image_url,
			'thumb_url'=>$thumb_url,
			'size'=>$file->getSize(),
		]);
		$this->previewJson($image_url,'image',$key,$file->getSize());
	}

	// 删除主图时同步删除缩略图
	protected function deleteRelated($row, string $www_path, string $filePath): void
	{
		$thumb_url=$row->getAttr($this->thumbUrlField);
		if($thumb_url!='')
		{
			$thumbPath=$www_path.str_replace(['/','\\'],XQ_DS,$thumb_url);
			if(is_file($thumbPath))
			{
				unlink($thumbPath);
			}
		}
	}
}
