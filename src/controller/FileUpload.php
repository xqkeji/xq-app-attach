<?php
/*
 * xqkeji.cn
 * @copyright 2021 新齐科技 (http://www.xqkeji.cn/)
 * @author 张文豪  <support@xqkeji.cn>
 */
namespace xqkeji\app\attach\controller;

use xqkeji\mvc\builder\Model;

/**
 * bootstrap-fileinput ajax 上传的公共流程（校验->落盘->入库->回显），供图片/文档控制器复用
 * 使用方需定义属性：$allowedFileExtensions、$maxSize、$maxFileCount、$fileType、$fileUrlField、$deleteMessage
 */
trait FileUpload
{
	// 落盘前校验钩子，校验失败调用 errorJson 后返回 null
	protected function beforeSave($file)
	{
		return true;
	}

	// 落盘后保存记录并返回 bootstrap-fileinput 的回显数据，返回 null 表示已输出错误
	abstract protected function buildResult($file, $desPath, $destFile, $uploadUrl);

	protected function post(string $name): string
	{
		return htmlspecialchars($_POST[$name] ?? '');
	}

	// 上传目录的模块名，默认取前端参数，控制器可固定覆盖
	protected function getModuleName(): string
	{
		return $this->post('moduleName');
	}

	protected function errorJson(array $error)
	{
		$this->returnJSON(['code' => $error['code'], 'error' => $error['error']]);
	}

	// 单个文件的完整校验流程，校验失败调用 errorJson 并返回 null
	protected function uploadFile($file)
	{
		if ($this->maxSize > 0 && ($file->getSize() / 1024 > $this->maxSize)) {
			$this->errorJson(['code' => 10002, 'error' => '超出最大文件大小']);
			return null;
		}
		if (!in_array(strtolower($file->getExtension()), $this->allowedFileExtensions)) {
			$this->errorJson(['code' => 10003, 'error' => '非法文件类型']);
			return null;
		}
		if ($this->beforeSave($file) !== true) {
			return null;
		}
		return $this->storeFile($file);
	}

	protected function previewJson(string $url, string $type, string $key, int $size, array $extra = [])
	{
		$this->returnJSON([
			'initialPreview' => [$url],
			'initialPreviewAsData' => true,
			'initialPreviewConfig' => [
				array_merge([
					'type' => $type,
					'caption' => $this->post('fileId'),
					'key' => $key,
					'size' => $size,
					'zoomData' => $url,
				], $extra),
			],
			'append' => true,
		]);
	}

	protected function saveRow(array $row): string
	{
		$model = Model::getModel();
		$model->save(array_merge([
			'type' => $this->fileType,
			'caption' => $this->post('fileId'),
			'size' => $row['size'],
			'auth_type' => $this->auth->getCurrentAuthType(),
			'auth_id' => $this->auth->getCurrentAuthId(),
			'module_name' => $this->getModuleName(),
			'controller_name' => $this->post('controllerName'),
			'action_name' => $this->post('actionName'),
			'action_params' => $this->post('actionParams'),
			'source_id' => '',
		], $row));
		return (string)$model->getKey();
	}

	public function upload()
	{
		$this->view->disable();
		if (!$this->request->hasFiles()) {
			$this->errorJson(['code' => 10000, 'error' => '没有文件上传']);
			return;
		}
		$files = $this->request->getUploadedFiles();
		if ($this->maxFileCount > 0 && count($files) > $this->maxFileCount) {
			$this->errorJson(['code' => 10001, 'error' => '超出最大文件数量']);
			return;
		}
		$file = current($files);
		$result = $this->uploadFile($file);
		if ($result === null) {
			return;
		}
		$this->buildResult($file, $result[0], $result[1], $result[2]);
	}

	// 按 模块/年月 建目录并落盘，返回 [相对目录, 相对文件, 访问URL前缀]，失败返回 null
	protected function storeFile($file)
	{
		$ds = XQ_DS;
		$upload_path = call_user_func(['xqkeji\\App', 'getUploadPath']);
		$upload_path_name = call_user_func(['xqkeji\\App', 'getUploadPathName']);
		$upload_url = '/' . $upload_path_name . '/';
		if (!file_exists($upload_path)) {
			mkdir($upload_path, 0777);
		}
		$des_path = $this->getModuleName();
		if (!file_exists($upload_path . $des_path . $ds)) {
			mkdir($upload_path . $des_path . $ds, 0777);
		}
		$des_path = $des_path . $ds . date('Ym');
		if (!file_exists($upload_path . $des_path . $ds)) {
			mkdir($upload_path . $des_path . $ds, 0777);
		}
		$file_name = date('YmdHis') . '_' . uniqid();
		$dest_file = $des_path . $ds . $file_name . '.' . $file->getExtension();
		if (!$file->moveTo($upload_path . $dest_file)) {
			$this->errorJson(['code' => 10006, 'error' => '文件上传失败']);
			return null;
		}
		return [$des_path, $dest_file, $upload_url];
	}

	public function delete()
	{
		$this->view->disable();
		$www_path = call_user_func(['xqkeji\\App', 'getWwwPath']);
		$key = htmlspecialchars($_POST['key'] ?? '');
		$model = Model::getModel();
		$row = $model->find($key);
		if (empty($row)) {
			$this->returnJSON(['key' => '', 'error' => '查询不到文件']);
			return;
		}
		$url = $row->getAttr($this->fileUrlField);
		$filePath = $www_path . str_replace(['/', '\\'], XQ_DS, $url);
		if (is_file($filePath)) {
			$this->deleteRelated($row, $www_path, $filePath);
			unlink($filePath);
			$this->returnJSON(['key' => $key, 'message' => $this->deleteMessage]);
		} else {
			$this->returnJSON(['key' => $key, 'error' => '文件不存在']);
		}
	}

	// 按规格生成缩略图，返回缩略图的相对路径，失败返回 null
	protected function makeThumb(string $extension, string $destFile, array $thumbConfig)
	{
		$upload_path = call_user_func(['xqkeji\\App', 'getUploadPath']);
		$thumb_file = substr($destFile, 0, -strlen('.' . $extension)) . '_thumb.' . $extension;
		$image = new \xqkeji\image\adapter\Gd($upload_path . $destFile);
		$image->resize($thumbConfig['width'], $thumbConfig['height'], $thumbConfig['master']);
		return $image->save($upload_path . $thumb_file) ? $thumb_file : null;
	}

	// 删除主文件时同步删除缩略图，控制器无 thumb_url 字段时自动跳过
	protected function deleteRelated($row, string $www_path, string $filePath): void
	{
		$field = $this->thumbUrlField ?? '';
		if ($field == '') {
			return;
		}
		$thumb_url = $row->getAttr($field);
		if ($thumb_url != '') {
			$thumbPath = $www_path . str_replace(['/', '\\'], XQ_DS, $thumb_url);
			if (is_file($thumbPath)) {
				unlink($thumbPath);
			}
		}
	}
}
