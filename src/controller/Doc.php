<?php
/*
 * xqkeji.cn
 * @copyright 2021 新齐科技 (http://www.xqkeji.cn/)
 * @author 张文豪  <support@xqkeji.cn>
 */
namespace xqkeji\app\attach\controller;

use xqkeji\mvc\Controller;

class Doc extends Controller
{
	use FileUpload;

	public $allowedFileExtensions=["doc","docx","pdf","wps","txt","zip","rar","tar","gz"];
	public $maxSize=2000;//以KB为单位
	public $maxFileCount=1;//上传最多的文件数
	public $fileType='';
	public $fileUrlField='file_url';
	public $deleteMessage='删除文档成功';

	protected function buildResult($file, $desPath, $destFile, $uploadUrl)
	{
		$file_url=$uploadUrl.str_replace('\\','/',$destFile);
		$key=$this->saveRow([
			'file_url'=>$file_url,
			'type'=>strtolower($file->getExtension()),// 文档记录实际扩展名
			'size'=>$file->getSize(),
		]);
		$this->previewJson($file_url,'other',$key,$file->getSize());
	}
}
