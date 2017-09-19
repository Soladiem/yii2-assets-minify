<?php

namespace soladiem\autoMinify;

use Yii;
use yii\base\ {
	BootstrapInterface,
	Component,
	Event
};
use yii\web\ {
	Application,
	JsExpression,
	Response,
	View
};
use yii\helpers\ {
	ArrayHelper,
	FileHelper,
	Html,
	Url
};
use JShrink\Minifier as JS;
use Minify_CSS as CSS;
use CssMin;
use soladiem\autoMinify\components\HtmlCompressor;


/**
 * Class AssetsMinify
 *
 * @author Denis Sitko <sitko.dv@gmail.com>
 * @copyright Semenov Alexander <semenov@skeeks.com>
 * @package soladiem\autoMinify
 */
class AssetsMinify extends Component implements BootstrapInterface
{
	/**
	 * Enable or disable the component
	 * @var bool
	 */
	public $enabled = true;

	/**
	 * Files excluded from assets
	 * @var array
	 */
	public $excludeFiles = [];

	/**
	 * Time in seconds for reading each asset file
	 * @var int
	 */
	public $readfileTimeout = 3;

	/**
	 * Enable minification js in html code
	 * @var bool
	 */
	public $jsMinifyHtml = true;

	/**
	 * Enable minification css in html code
	 * @var bool
	 */
	public $cssMinifyHtml = true;

	/**
	 * Cut comments during processing js
	 * @var bool
	 */
	public $jsCutFlaggedComments = true;

	/**
	 * Cut comments during processing css
	 * @var bool
	 */
	public $cssCutFlaggedComments = true;

	/**
	 * @var array
	 */
	protected $jsOptions = [];

	/**
	 * @var array
	 */
	protected $cssOptions = [];

	/**
	 * Turning association JS files
	 * @var bool
	 */
	public $jsFileCompile = true;

	/**
	 * Turning association CSS files
	 * @var bool
	 */
	public $cssFileCompile = true;

	/**
	 * Compile JS file with downloading a remote file
	 * @var bool
	 */
	public $jsFileRemoteCompile = false;

	/**
	 * Compile CSS file with downloading a remote file
	 * @var bool
	 */
	public $cssFileRemoteCompile = false;

	/**
	 * Compress JS file
	 * @var bool
	 */
	public $jsFileCompress = true;

	/**
	 * Compress CSS file
	 * @var bool
	 */
	public $cssFileCompress = true;

	/**
	 * Enable compression HTML
	 * @var bool
	 */
	public $htmlCompress = true;

	/**
	 * @var array
	 */
	public $htmlCompressOptions = [
		'extra'       => false,
		'no-comments' => true
	];

	/**
	 * Moving down the page css files
	 * @var bool
	 */
	public $cssFileBottom = true;

	/**
	 * Transfer css file down the page and uploading them using js
	 * @var bool
	 */
	public $cssFileBottomLoadOnJs = false;

	/**
	 * Do not include js files in Pjax
	 * @var bool
	 */
	public $noIncludeJsFilesOnPjax = true;

	/**
	 * Path asset compile css file
	 * @var string
	 */
	public $pathCompileCssFile = 'css';

	/**
	 * Path asset compile js file
	 * @var string
	 */
	public $pathCompileJsFile = 'js';

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		if (!Yii::$app instanceof Application) {
			return;
		}

		parent::init();
	}

	/**
	 * Bootstrap method to be called during application bootstrap stage.
	 * @param \yii\base\Application $app the application currently running
	 */
	public function bootstrap($app)
	{
		$app->view->on(View::EVENT_END_PAGE, function (Event $e) use ($app) {
			/**
			 * @var $view \yii\web\View
			 */
			$view = $e->sender;

			if ($this->enabled && $view instanceof View &&
				$app->response->format == Response::FORMAT_HTML &&
				!$app->request->isAjax && !$app->request->isPjax) {

				Yii::beginProfile('Compress assets');
				$this->_compress($view);
				Yii::endProfile('Compress assets');
			}

			if ($this->enabled && $app->request->isPjax && $this->noIncludeJsFilesOnPjax) {
				Yii::$app->view->jsFiles = null;
			}
		});

		/**
		 * HTML compressing
		 */
		$app->response->on(Response::EVENT_BEFORE_SEND, function (Event $e) use ($app) {
			/**
			 * @var $view \yii\web\View
			 */
			$response = $e->sender;

			if ($this->enabled && $this->htmlCompress &&
				$response->format == Response::FORMAT_HTML &&
				!$app->request->isAjax && !$app->request->isPjax) {
				if (!empty($response->data)) {
					$response->data = $this->compressHtml($response->data);
				}

				if (!empty($response->content)) {
					$response->content = $this->compressHtml($response->content);
				}
			}
		});
	}

	/**
	 * Compress css and js files
	 * @param View $view
	 */
	protected function _compress(View $view)
	{
		// Compiling js files into one
		if ($view->jsFiles && $this->jsFileCompile) {
			// Excluding specified js files
			foreach ($view->jsFiles as $jsFile => $v) {
				if (in_array(basename($jsFile), $this->excludeFiles)){
					unset($view->jsFiles[$jsFile]);
				}
			}

			Yii::beginProfile('Compress JS files');
			foreach ($view->jsFiles as $pos => $files) {
				if ($files) {
					$view->jsFiles[$pos] = $this->compileJsFiles($files);
				}
			}
			Yii::endProfile('Compress JS files');
		}

		// Compiling js code on the page
		if ($view->js && $this->jsMinifyHtml) {
			Yii::beginProfile('Compress JS code');
			foreach ($view->js as $pos => $parts) {
				if ($parts) {
					$view->js[$pos] = $this->compressJs($parts);
				}
			}
			Yii::endProfile('Compress JS code');
		}

		// Compiling css files into one
		if ($view->cssFiles && $this->cssFileCompile) {
			// Excluding specified css files
			foreach ($view->cssFiles as $cssFile => $v) {
				if (in_array(basename($cssFile), $this->excludeFiles)){
					unset($view->cssFiles[$cssFile]);
				}
			}

			Yii::beginProfile('Compress CSS files');
			$view->cssFiles = $this->compileCssFiles($view->cssFiles);
			Yii::endProfile('Compress CSS files');
		}

		// Compiling css code on the page
		if ($view->css && $this->cssMinifyHtml) {
			Yii::beginProfile('Compress CSS code');
			$view->css = $this->compressCss($view->css);
			Yii::endProfile('Compress CSS code');
		}
		
		// Move CSS down
		if ($view->cssFiles && $this->cssFileBottom)
		{
			Yii::beginProfile('Moving CSS files bottom');
			if ($this->cssFileBottomLoadOnJs)
			{
				Yii::beginProfile('Load CSS on JS');
				$cssFilesString = implode('', $view->cssFiles);
				$view->cssFiles = [];
				$script = Html::script(new JsExpression(<<<JS
        document.write('{$cssFilesString}');
JS
				));
				if (ArrayHelper::getValue($view->jsFiles, View::POS_END)){
					$view->jsFiles[View::POS_END] = ArrayHelper::merge($view->jsFiles[View::POS_END], [$script]);
				} else {
					$view->jsFiles[View::POS_END][] = $script;
				}
				Yii::endProfile('Load CSS on JS');
			} else {
				if (ArrayHelper::getValue($view->jsFiles, View::POS_END)){
					$view->jsFiles[View::POS_END] = ArrayHelper::merge($view->cssFiles, $view->jsFiles[View::POS_END]);
				} else {
					$view->jsFiles[View::POS_END] = $view->cssFiles;
				}
				$view->cssFiles = [];
			}
			Yii::endProfile('Moving CSS files bottom');
		}
	}

	/**
	 * Get hash class
	 * @return string
	 */
	private function hash(): string
	{
		return serialize((array)$this);
	}

	/**
	 * Compile JS files
	 * @param array $files
	 * @return array
	 */
	protected function compileJsFiles($files = []): array
	{
		$fileName = md5(implode(array_keys($files)) . $this->hash()) . '.js';
		$publicUrl = Yii::getAlias("@web/{$this->pathCompileJsFile}/$fileName");
		$rootDir = Yii::getAlias('@webroot/' . $this->pathCompileJsFile);
		$rootUrl = $rootDir . '/' . $fileName;

		if (file_exists($rootUrl)) {
			$resultFiles = [];

			foreach ($files as $fileCode => $fileTag) {
				if (!Url::isRelative($fileCode)) {
					$resultFiles[$fileCode] = $fileTag;
				} else {
					if ($this->jsFileRemoteCompile) {
						$resultFiles[$fileCode] = $fileTag;
					}
				}
			}

			$publicUrl = $publicUrl . '?v=' . filemtime($rootUrl);
			$resultFiles[$publicUrl] = Html::jsFile($publicUrl, $this->jsOptions);
			return $resultFiles;
		}

		try {
			$resultContent = [];
			$resultFiles = [];

			foreach ($files as $fileCode => $fileTag) {
				if (Url::isRelative($fileCode)) {
					$contentFile = $this->fileGetContents(Url::to(Yii::getAlias($fileCode), true));
					$resultContent[] = trim($contentFile) . "\n;";;
				} else {
					if ($this->jsFileRemoteCompile) {
						$contentFile = $this->fileGetContents($fileCode);
						$resultContent[] = trim($contentFile);
					} else {
						$resultFiles[$fileCode] = $fileTag;
					}
				}
			}
		} catch (\Exception $e) {
			Yii::error($e->getMessage(), static::className());
			return $files;
		}

		if ($resultContent) {
			$content = implode($resultContent, ";\n");
			if (!is_dir($rootDir)) {
				if (!FileHelper::createDirectory($rootDir, 0777)) {
					return $files;
				}
			}

			if ($this->jsFileCompress) {
				$content = JS::minify($content, [
					'flaggedComments' => $this->jsCutFlaggedComments
				]);
			}

			$page = Yii::$app->request->absoluteUrl;
			$useFunction = function_exists('curl_init') ? 'curl extension' : 'php file_get_contents';
			$filesString = implode(', ', array_keys($files));

			Yii::info("Create js file: {$publicUrl} from files: {$filesString} to use {$useFunction} on page '{$page}'", static::className());

			$file = fopen($rootUrl, 'w');
			fwrite($file, $content);
			fclose($file);
		}

		if (file_exists($rootUrl)) {
			$publicUrl = $publicUrl . '?v=' . filemtime($rootUrl);
			$resultFiles[$publicUrl] = Html::jsFile($publicUrl, $this->jsOptions);
			return $resultFiles;
		} else {
			return $files;
		}
	}

	/**
	 * Compile CSS files
	 * @param array $files
	 * @return array
	 */
	protected function compileCssFiles($files = []): array
	{
		$fileName = md5(implode(array_keys($files)) . $this->hash()) . '.css';

		$publicUrl = Yii::getAlias("@web/{$this->pathCompileCssFile}/$fileName");
		$rootDir = Yii::getAlias('@webroot/' . $this->pathCompileCssFile);
		$rootUrl = $rootDir . '/' . $fileName;

		if (file_exists($rootUrl)) {
			$resultFiles = [];

			foreach ($files as $fileCode => $fileTag) {
				if (!Url::isRelative($fileCode) && !$this->cssFileRemoteCompile) {
					$resultFiles[$fileCode] = $fileTag;
				}
			}

			$publicUrl = $publicUrl . '?v=' . filemtime($rootUrl);
			$resultFiles[$publicUrl] = Html::cssFile($publicUrl, $this->cssOptions);
			return $resultFiles;
		}

		try {
			$resultContent = [];
			$resultFiles = [];

			foreach ($files as $fileCode => $fileTag) {
				if (Url::isRelative($fileCode)) {
					$contentTmp = trim($this->fileGetContents(Url::to(\Yii::getAlias($fileCode), true)));
					$fileCodeTmp = explode('/', $fileCode);
					unset($fileCodeTmp[count($fileCodeTmp) - 1]);
					$prependRelativePath = implode('/', $fileCodeTmp) . '/';

					$contentTmp = CSS::minify($contentTmp, [
						'prependRelativePath' => $prependRelativePath,
						'compress'         => true,
						'removeCharsets'   => true,
						'preserveComments' => true,
					]);
					$resultContent[] = $contentTmp;
				} else {
					if ($this->cssFileRemoteCompile) {
						$contentFile = $this->fileGetContents($fileCode);
						$resultContent[] = trim($contentFile);
					} else {
						$resultFiles[$fileCode] = $fileTag;
					}
				}
			}

		} catch (\Exception $e) {
			Yii::error($e->getMessage(), static::className());
			return $files;
		}

		if ($resultContent) {
			$content = implode($resultContent, "\n");
			if (!is_dir($rootDir)) {
				if (!FileHelper::createDirectory($rootDir, 0777)) {
					return $files;
				}
			}

			if ($this->cssFileCompress) {
				$content = CssMin::minify($content, [
					'RemoveComments' => $this->cssCutFlaggedComments
				]);
			}

			$page = Yii::$app->request->absoluteUrl;
			$useFunction = function_exists('curl_init') ? 'curl extension' : 'php file_get_contents';
			$filesString = implode(', ', array_keys($files));

			Yii::info("Create css file: {$publicUrl} from files: {$filesString} to use {$useFunction} on page '{$page}'", static::className());

			$file = fopen($rootUrl, 'w');
			fwrite($file, $content);
			fclose($file);
		}

		if (file_exists($rootUrl)) {
			$publicUrl = $publicUrl . '?v=' . filemtime($rootUrl);
			$resultFiles[$publicUrl] = Html::cssFile($publicUrl, $this->cssOptions);
			return $resultFiles;
		} else {
			return $files;
		}
	}

	/**
	 * Compress Js code
	 * @param $parts
	 * @return array
	 */
	protected function compressJs($parts): array
	{
		$result = [];
		if ($parts) {
			foreach ($parts as $key => $value) {
				$result[$key] = JS::minify($value, [
					'flaggedComments' => $this->jsCutFlaggedComments
				]);
			}
		}
		return $result;
	}

	/**
	 * Compress Css code
	 * @param array $css
	 * @return array
	 */
	protected function compressCss($css = []): array
	{
		$newCss = [];
		foreach ($css as $code => $value) {
			$newCss[] = preg_replace_callback('/<style\b[^>]*>(.*)<\/style>/is', function ($match) {
				return $match[1];
			}, $value);
		}
		$css = implode($newCss, "\n");
		$css = CssMin::minify($css, [
			'RemoveComments' => $this->cssCutFlaggedComments
		]);
		return [md5($css) => '<style>' . $css . '</style>'];
	}

	/**
	 * Compress HTML code
	 * @param $html
	 * @return mixed
	 */
	protected function compressHtml($html)
	{
		$options = $this->htmlCompressOptions;
		return HtmlCompressor::compress($html, $options);
	}

	/**
	 * Get remote file
	 * @param $file
	 * @return mixed|string
	 * @throws \Exception
	 */
	private function fileGetContents($file)
	{
		if (function_exists('curl_init')) {
			$url = $file;
			$ch = curl_init();
			$timeout = (int)$this->readfileTimeout;

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

			$result = curl_exec($ch);
			if ($result === false) {
				$errorMessage = curl_error($ch);
				curl_close($ch);

				throw new \Exception($errorMessage);
			}
		}

		if (function_exists('curl_init')) {
			$url = $file;
			$ch = curl_init();
			$timeout = (int)$this->readfileTimeout;

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

			$result = curl_exec($ch);
			if ($result === false) {
				$errorMessage = curl_error($ch);
				curl_close($ch);

				throw new \Exception($errorMessage);
			}

			$info = curl_getinfo($ch);
			if (isset($info['http_code']) && !ArrayHelper::isIn(ArrayHelper::getValue($info, 'http_code'), [200])) {
				curl_close($ch);
				throw new \Exception("File not found: {$file}");
			}

			curl_close($ch);
			return $result;
		} else {
			$ctx = stream_context_create(['http' => ['timeout' => (int)$this->readfileTimeout]]);
			return file_get_contents($file, false, $ctx);
		}
	}
}
