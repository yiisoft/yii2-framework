<?php

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\web;

/**
 * UrlRuleInterface is the interface that should be implemented by URL rule classes.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
interface UrlRuleInterface
{
    /**
     * Parses the given request and returns the corresponding route and parameters.
     * @param UrlManager $manager the URL manager
     * @param Request $request the request component
     * @return array|bool the parsing result. The route and the parameters are returned as an array.
     * If false, it means this rule cannot be used to parse this path info.
     */
    public function parseRequest($manager, $request);

    /**
     * Creates a URL according to the given route and parameters.
     * @param UrlManager $manager the URL manager
     * @param string $route the route. It should not have slashes at the beginning or the end.
     * @param array $params the parameters
     * @return string|bool the created URL, or false if this rule cannot be used for creating this URL.
     */
    public function createUrl($manager, $route, $params);
}
