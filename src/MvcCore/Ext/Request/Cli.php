<?php

/**
 * MvcCore
 *
 * This source file is subject to the BSD 3 License
 * For the full copyright and license information, please view
 * the LICENSE.md file that are distributed with this source code.
 *
 * @copyright	Copyright (c) 2016 Tom Flídr (https://github.com/mvccore/mvccore)
 * @license		https://mvccore.github.io/docs/mvccore/4.0.0/LICENCE.md
 */

namespace MvcCore\Ext\Request;

/**
 * MvcCore Cli Request extension:
 * - linear request url parsing from $_SERVER into local properties describing app root and params
 * - params reading from $_SERVER['argv'] in double dash, single dash or no dash form with or without equal char
 * - params cleaning by developer rules
 */
class Cli extends \MvcCore\Request
{
	/**
	 * Application console mode. TRUE if application is running in console mode (php_sapi_name() == 'cli').
	 * @var bool
	 */
	public $Console		= FALSE;

    /**
	 * Get everytime new instance of http request,
	 * global variables should be changed and injected here
	 * to get different request object from currently called real request.
	 *
	 * @param array $server
	 * @param array $get
	 * @param array $post
	 */
    public function __construct (array & $server, array & $get, array & $post) {
		$this->serverGlobals = $server;
		$this->getGlobals = $get;
		$this->postGlobals = $post;
		
		$this->Console = php_sapi_name() == 'cli';

		$this->initScriptName();
		$this->initAppRoot();
		if ($this->Console) {
			$this->initCliParams();
		} else {
			$this->initMethod();
			$this->initBasePath();
			$this->initProtocol();
			$this->initParsedUrlSegments();
			$this->initHttpParams();
			$this->initPath();
			$this->initReferer();
			$this->initUrlCompositions();
		}

		unset($this->serverGlobals, $this->getGlobals, $this->postGlobals);
	}

	/**
	 * Initialize index.php script name.
	 * @return void
	 */
	protected function initScriptName () {
		$this->indexScriptName = str_replace('\\', '/', $this->serverGlobals['SCRIPT_NAME']);
		if (!$this->Console)
			$this->ScriptName = '/' . substr($this->indexScriptName, strrpos($this->indexScriptName, '/') + 1);
	}

	/**
	 * Initialize application root directory.
	 * @return void
	 */
	protected function initAppRoot () {
		// $appRootRelativePath = mb_substr($this->indexScriptName, 0, strrpos($this->indexScriptName, '/') + 1);
		// ucfirst - cause IIS has lower case drive name here - different from __DIR__ value
		$indexFilePath = $this->Console
			? str_replace('\\', '/', getcwd()) . '/' . $_SERVER['SCRIPT_FILENAME']
			: ucfirst(str_replace('\\', '/', $this->serverGlobals['SCRIPT_FILENAME']));
		if (strpos(__FILE__, 'phar://') === 0) {
			$appRootFullPath = 'phar://' . $indexFilePath;
		} else {
			$appRootFullPath = substr($indexFilePath, 0, mb_strrpos($indexFilePath, '/'));
		}
		$this->AppRoot = str_replace(array('\\', '//'), '/', $appRootFullPath);
	}

	/**
	 * Initialize params from cli.
	 * @example
	 *		php index.php -c index -a index --id 10 --switch -sw
	 *		php index.php --controller index --action index --id 10 --switch --s --w
	 *		php index.php --controller=index --action=index -id 10 --switch -sw
	 * @return void
	 */
	protected function initCliParams () {
		$rawArgs = $this->serverGlobals['argv'];
		if (isset($rawArgs[0]) && $rawArgs[0] == $this->indexScriptName) array_shift($rawArgs);
		$params = array();
		$dash = '-';
		for ($i = 0, $l = count($rawArgs); $i < $l; $i += 1) {
			$rawArg = $rawArgs[$i];
			$nextRawArg = isset($rawArgs[$i + 1]) ? $rawArgs[$i + 1] : NULL;
			$nextRawArgIsValue = !is_null($nextRawArg) && substr($nextRawArg, 0, 1) != $dash;
			$rawArgLength = strlen($rawArg);
			if ($rawArgLength > 1) {
				$firstChar = substr($rawArg, 0, 1);
				$secondChar = substr($rawArg, 1, 1);
				if ($firstChar == $dash && $secondChar != $dash) {
					$i = $this->initCliParamSingleDash($i, $params, $rawArg, $nextRawArg, $nextRawArgIsValue);
				} else if ($firstChar == $dash && $secondChar == $dash) {
					$i = $this->initCliParamDoubleDash($i, $params, $rawArg, $nextRawArg, $nextRawArgIsValue);
				} else {
					$this->initCliParamNoDash($params, $rawArg);
				}
			}
		}
		$this->initCliParamsCleanQuotes($params);
		$this->Params = $params;
	}

	/**
	 * Initialize params from cli, starting with single dash, with value assigned by equal char or after space as another query part if any, else boolean true.
	 * @return void
	 */
	protected function initCliParamSingleDash ($i, & $params, & $rawArg, & $nextRawArg, & $nextRawArgIsValue) {
		// single dash param: -c | -a | -o | -opq
		$param = substr($rawArg, 1);
		$equalPos = strpos($param, '=');
		if ($equalPos !== FALSE) {
			// equal character:
			$params[substr($param, 0, $equalPos)] = substr($param, $equalPos + 1);
		} else {
			// no equal character:
			if (strlen($param) > 1 && !$nextRawArgIsValue) {
				for ($j = 0, $k = strlen($param); $j < $k; $j += 1) $params[$param[$j]] = TRUE;
			} else {
				if ($param == 'c' && $nextRawArgIsValue) {
					$params['controller'] = $nextRawArg;
				} else if ($param == 'a' && $nextRawArgIsValue) {
					$params['action'] = $nextRawArg;
				} else {
					$params[$param] = $nextRawArg;
				}
				$i += 1;
			}
		}
		return $i;
	}

	/**
	 * Initialize params from cli, starting with double dash, with value assigned by equal char or after space as another query part if any, else boolean true.
	 * @return void
	 */
	protected function initCliParamDoubleDash ($i, & $params, & $rawArg, & $nextRawArg, & $nextRawArgIsValue) {
		// double dash param: --controller controllerValue | --action actionValue | -other anyOtherValue
		$param = substr($rawArg, 2);
		$equalPos = strpos($param, '=');
		if ($equalPos !== FALSE) {
			// equal character:
			$params[substr($param, 0, $equalPos)] = substr($param, $equalPos + 1);
		} else if ($nextRawArgIsValue) {
			// no equal character:
			$params[$param] = $nextRawArg;
			$i += 1;
		} else {
			$params[$param] = TRUE;
		}
		return $i;
	}

	/**
	 * Initialize params from cli, starting without dash, with value assigned by equal char or after space as another query part if any, else boolean true.
	 * @return void
	 */
	protected function initCliParamNoDash (& $params, & $rawArg) {
		$equalPos = strpos($rawArg, '=');
		if ($equalPos !== FALSE) {
			// equal character:
			$params[substr($rawArg, 0, $equalPos)] = substr($rawArg, $equalPos + 1);
		} else {
			// no equal character:
			array_push($params, $rawArg);
		}
	}

	/**
	 * Initialize params from cli, clean trailing quotes: " ' `
	 * @return void
	 */
	protected function initCliParamsCleanQuotes (& $params) {
		$quotChars = array('"', "'", '`');
		foreach ($params as & $value) {
			foreach ($quotChars as $quotChar) {
				if (mb_strpos($value, $quotChar) !== 0) continue;
				if (mb_strrpos($value, $quotChar) !== mb_strlen($value) - 1) continue;
				$value = trim($value, $quotChar);
			}
		}
	}
}