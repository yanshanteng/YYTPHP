<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

define('ROOT_PATH', dirname(__FILE__));

require '../YYTPHP/Y.php';

Y::config('debug', true);

Y::run(ROOT_PATH.'/controller');