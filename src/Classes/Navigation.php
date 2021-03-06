<?php

/**
 * This file is part of Laravel Navigation by Graham Campbell.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace GrahamCampbell\Navigation\Classes;

use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use GrahamCampbell\HTMLMin\Classes\HTMLMin;

/**
 * This is the navigation class.
 *
 * @package    Laravel-Navigation
 * @author     Graham Campbell
 * @copyright  Copyright 2013-2014 Graham Campbell
 * @license    https://github.com/GrahamCampbell/Laravel-Navigation/blob/master/LICENSE.md
 * @link       https://github.com/GrahamCampbell/Laravel-Navigation
 */
class Navigation
{
    /**
     * The items in the main nav bar.
     *
     * @var array
     */
    protected $main = array();

    /**
     * The items in the bar nav bar.
     *
     * @var array
     */
    protected $bar = array();

    /**
     * The events instance.
     *
     * @var \Illuminate\Events\Dispatcher
     */
    protected $events;

    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The url instance.
     *
     * @var \Illuminate\Routing\UrlGenerator
     */
    protected $url;

    /**
     * The htmlmin instance.
     *
     * @var \GrahamCampbell\HTMLMin\Classes\HTMLMin
     */
    protected $htmlmin;

    /**
     * The view name.
     *
     * @var string
     */
    protected $view;

    /**
     * Create a new instance.
     *
     * @param  \Illuminate\Events\Dispatcher  $events
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Routing\UrlGenerator  $url
     * @param  \GrahamCampbell\HTMLMin\Classes\HTMLMin  $htmlmin
     * @param  string  $view
     * @return void
     */
    public function __construct(Dispatcher $events, Request $request, UrlGenerator $url, HTMLMin $htmlmin, $view)
    {
        $this->events = $events;
        $this->request = $request;
        $this->url = $url;
        $this->htmlmin = $htmlmin;
        $this->view = $view;
    }

    /**
     * Get the main navigation array.
     *
     * @param  string  $type
     * @return array
     */
    public function getMain($type = 'default')
    {
        // fire event that can be hooked to add items to the nav bar
        $this->events->fire('navigation.main', array(array('type' => $type)));

        // check if the type exists in the main array
        if ($type !== 'default' && !array_key_exists($type, $this->main)) {
            // use the default type
            $type = 'default';
        }

        if (!array_key_exists($type, $this->main)) {
            // add it if it doesn't exists
            $this->main[$type] = array();
        }

        // apply active keys
        $nav = $this->active($this->main[$type]);

        // fix up and spit out the nav bar
        return $this->process($nav);
    }

    /**
     * Get the bar navigation array.
     *
     * @param  string  $type
     * @return array
     */
    public function getBar($type = 'default')
    {
        // fire event that can be hooked to add items to the nav bar
        $this->events->fire('navigation.bar', array(array('type' => $type)));

        // check if the type exists in the bar array
        if ($type !== 'default' && !array_key_exists($type, $this->bar)) {
            // use the default type
            $type = 'default';
        }

        if (!array_key_exists($type, $this->bar)) {
            // add it if it doesn't exists
            $this->bar[$type] = array();
        }

        // don't apply active keys
        $nav = $this->bar[$type];

        // fix up and spit out the nav bar
        return $this->process($nav);
    }

    /**
     * Add an item to the main navigation array.
     *
     * @param  array   $item
     * @param  string  $type
     * @param  bool    $first
     * @return $this
     */
    public function addMain(array $item, $type = 'default', $first = false)
    {
        // check if the type exists in the main array
        if (!array_key_exists($type, $this->main)) {
            // add it if it doesn't exists
            $this->main[$type] = array();
        }

        // check if we are forcing the item to the start
        if ($first) {
            // add the item to the start of the array
            $this->main[$type] = array_merge(array($item), $this->main[$type]);
        } else {
            // add the item to the end of the array
            $this->main[$type][] = $item;
        }

        return $this;
    }

    /**
     * Add an item to the bar navigation array.
     *
     * @param  array   $item
     * @param  string  $type
     * @param  bool    $first
     * @return $this
     */
    public function addBar(array $item, $type = 'default', $first = false)
    {
        // check if the type exists in the bar array
        if (!array_key_exists($type, $this->bar)) {
            // add it if it doesn't exists
            $this->bar[$type] = array();
        }

        // check if we are forcing the item to the start
        if ($first) {
            // add the item to the start of the array
            $this->bar[$type] = array_merge(array($item), $this->bar[$type]);
        } else {
            // add the item to the end of the array
            $this->bar[$type][] = $item;
        }

        return $this;
    }

    /**
     * Get the navigation bar html.
     *
     * @param  string  $maintype
     * @param  string|bool   $bartype
     * @param  array  $data
     * @return string
     */
    public function getHTML($maintype = 'default', $bartype = false, array $data = array('title' => 'Navigation', 'side' => 'dropdown', 'inverse' => true))
    {
        // get the nav bar arrays
        $mainnav = $this->getMain($maintype);
        if ($bartype) {
            $barnav = $this->getBar($bartype);
            if (empty($barnav)) {
                $barnav = false;
            }
        } else {
            $barnav = false;
        }

        // return the html nav bar
        return $this->htmlmin->make($this->view, array_merge($data, array('main' => $mainnav, 'bar' => $barnav)));
    }

    /**
     * Check if each item is active.
     *
     * @param  array  $nav
     * @return array
     */
    protected function active(array $nav)
    {
        // check if each item is active
        foreach ($nav as $key => $value) {
            // check if it is local
            if (isset($value['slug'])) {
                // if the request starts with the slug
                if ($this->request->is($value['slug']) || $this->request->is($value['slug'].'/*')) {
                    // then the navigation item is active, or selected
                    $nav[$key]['active'] = true;
                } else {
                    // then the navigation item is not active or selected
                    $nav[$key]['active'] = false;
                }
            } else {
                // then the navigation item is not active or selected
                $nav[$key]['active'] = false;
            }
        }

        // spit out the nav bar array at the end
        return $nav;
    }

    /**
     * Convert slugs to urls.
     *
     * @param  array  $nav
     * @return array
     */
    protected function process(array $nav)
    {
        // convert slugs to urls
        foreach ($nav as $key => $value) {
            // if the url is not set
            if (!isset($value['url'])) {
                // set the url based on the slug
                $nav[$key]['url'] = $this->url->to($value['slug']);
            }
            // remove any remaining slugs
            unset($nav[$key]['slug']);
        }

        // spit out the nav bar array at the end
        return $nav;
    }

    /**
     * Get the events instance.
     *
     * @return \Illuminate\Events\Dispatcher
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Get the request instance.
     *
     * @return \Illuminate\Http\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get the url instance.
     *
     * @return \Illuminate\Routing\UrlGenerator
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Get the htmlmin instance.
     *
     * @return \GrahamCampbell\HTMLMin\Classes\HTMLMin
     */
    public function getHTMLMin()
    {
        return $this->htmlmin;
    }
}
