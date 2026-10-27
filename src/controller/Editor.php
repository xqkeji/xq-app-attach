<?php
/*
 * xqkeji.cn
 * @copyright 2021 新齐科技 (http://www.xqkeji.cn/)
 * @author 张文豪  <support@xqkeji.cn>
 */
namespace xqkeji\app\attach\controller;

use xqkeji\mvc\Controller;
use xqkeji\mvc\builder\Model;

/**
 * hugerte(tinymce) 富文本编辑器的文件上传控制器
 * 接口约定：上传成功的响应为 {"location":"文件URL"}，失败为 {"location":"0","error":"..."}
 * 上传的文件会写入附件表留痕（module_name=editor），便于统一统计/清理
 */
class Editor extends Controller
{
	use FileUpload;

	public $imageExtensions=["jpg","jpeg","gif","png"];
	public $docExtensions=["doc","docx","pdf","wps","txt","zip","rar","tar","gz"];
	public $allowedFileExtensions=[];//upload() 中按图片+文档合并
	public $maxSize=0;//upload() 中按文件类型取对应限制
	public $maxFileCount=1;
	public $imageMaxSize=1000;//图片大小限制，以KB为单位
	public $docMaxSize=2000;//文档大小限制，以KB为单位
	public $maxWidth=0;//限制图片最大宽度
	public $maxHeight=0;//限制图片最大高度
	public $browseThumb=['width'=>200,'height'=>null,'master'=>\xqkeji\image\Enum::WIDTH];//浏览列表预览图规格
	public $browsePageSize=20;//浏览列表每页条数
	public $moduleName='editor';//上传目录的模块名
	public $fileUrlField='file_url';
	public $thumbUrlField='thumb_url';
	public $deleteMessage='删除文件成功';

	// 编辑器上传单个文件，通过扩展名区分图片/文档并应用各自的大小限制
	public function upload()
	{
		if (!$this->request->hasFiles()) {
			$this->errorJson(['code' => 10000, 'error' => '没有文件上传']);
			return;
		}
		$this->allowedFileExtensions=array_merge($this->imageExtensions,$this->docExtensions);
		$file=current($this->request->getUploadedFiles());
		$isImage=in_array(strtolower($file->getExtension()),$this->imageExtensions,true);
		$this->maxSize=$isImage?$this->imageMaxSize:$this->docMaxSize;
		$result=$this->uploadFile($file);
		if($result===null)
		{
			return;
		}
		$this->buildResult($file,$result[0],$result[1],$result[2]);
	}

	// 编辑器接口约定：失败的响应同样带 location='0'
	protected function errorJson(array $error)
	{
		$this->returnJSON(['location'=>'0','error'=>$error['error']]);
	}

	protected function getModuleName(): string
	{
		return $this->moduleName;
	}

	protected function beforeSave($file)
	{
		if(in_array(strtolower($file->getExtension()),$this->imageExtensions,true))
		{
			if(!\xqkeji\image\adapter\Gd::check())
			{
				$this->errorJson(['code'=>10007,'error'=>'PHP扩展GD没有安装']);
				return null;
			}
			// 限制上传图片的宽或高 (注意：0 表示无限制)
			if($this->maxWidth>0 || $this->maxHeight>0)
			{
				list ($w,$h)=@getimagesize($file->getTempName());
				if($this->maxWidth>0 && $w>$this->maxWidth)
				{
					$this->errorJson(['code'=>10004,'error'=>'图片超出最大的宽度']);
					return null;
				}
				if($this->maxHeight>0 && $h>$this->maxHeight)
				{
					$this->errorJson(['code'=>10005,'error'=>'图片超出最大的高度']);
					return null;
				}
			}
		}
		return true;
	}

	protected function buildResult($file, $desPath, $destFile, $uploadUrl)
	{
		$extension=strtolower($file->getExtension());
		$file_url=$uploadUrl.str_replace('\\','/',$destFile);
		$thumb_url='';
		// 图片生成预览缩略图，供 browse 的图库使用
		if(in_array($extension,$this->imageExtensions,true))
		{
			$thumb_file=$this->makeThumb($extension,$destFile,$this->browseThumb);
			if($thumb_file===null)
			{
				$this->errorJson(['code'=>10008,'error'=>'生成缩略图失败']);
				return;
			}
			$thumb_url=$uploadUrl.str_replace('\\','/',$thumb_file);
		}
		$this->saveRow([
			'file_url'=>$file_url,
			'thumb_url'=>$thumb_url,
			'type'=>$extension,
			'caption'=>htmlspecialchars($file->getName()),
			'size'=>$file->getSize(),
		]);
		$this->returnJSON(['location'=>$file_url]);
	}

	// 供 file_picker_callback 的浏览弹窗取数据：?types=jpg,png&page=1&page_size=20
	// types 为扩展名列表（逗号分隔），不传则返回全部；全站范围，不限上传人
	public function browse()
	{
		$this->view->disable();
		$allowed=array_merge($this->imageExtensions,$this->docExtensions);
		$types=[];
		if(isset($_GET['types']) && $_GET['types']!='')
		{
			foreach(explode(',',htmlspecialchars($_GET['types'])) as $type)
			{
				$type=trim($type);
				if(in_array($type,$allowed,true))
				{
					$types[]=$type;
				}
			}
			if(empty($types))
			{
				$this->returnJSON(['error'=>'非法文件类型']);
				return;
			}
		}
		$page=max((int)($_GET['page'] ?? 1),1);
		$pageSize=(int)($_GET['page_size'] ?? 0)>0?(int)$_GET['page_size']:$this->browsePageSize;
		// Image 控制器入库的 type 固定为 'image'（不记扩展名），图片浏览需一并命中
		if(array_intersect($types,$this->imageExtensions))
		{
			$types[]='image';
		}

		$model=Model::getModel();
		$rows=$this->filterTypes($model,$types)->order(['id'=>'desc'])->limit(($page-1)*$pageSize,$pageSize)->findAll();
		$rows=is_array($rows)?$rows:[];
		$list=[];
		foreach($rows as $row)
		{
			$url=$this->value($row,'file_url')?:$this->value($row,'image_url');
			if((string)$url==='')
			{
				continue;
			}
			$list[]=[
				'key'=>$this->value($row,'id'),
				'url'=>$url,
				'thumb'=>$this->value($row,'thumb_url')?:$url,
				'name'=>$this->value($row,'caption')?:basename(str_replace('\\','/',$url)),
				'size'=>(int)$this->value($row,'size'),
				'type'=>$this->value($row,'type'),
			];
		}
		$this->returnJSON([
			'list'=>$list,
			'page'=>$page,
			'page_size'=>$pageSize,
			'more'=>count($rows)>=$pageSize,//无计数接口，用满页推断是否还有下一页
		]);
	}

	// 按类型过滤（框架 where 的多值签名如与此处不同，只需改这一个方法）
	protected function filterTypes($model, array $types)
	{
		return empty($types) ? $model : $model->where('type', $types);
	}

	// 兼容关联数组与行对象两种取值形态
	protected function value($row,string $field): string
	{
		if(is_array($row))
		{
			return (string)($row[$field] ?? '');
		}
		return (string)$row->getAttr($field);
	}
}
