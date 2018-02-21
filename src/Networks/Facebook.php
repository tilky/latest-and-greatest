<?php

namespace LatestAndGreatest\Networks;

use LatestAndGreatest\LatestAndGreatest;
use Facebook\Facebook as FB;
use Exception;

/**
 * Extend Latest And Greatest To Support Facebook
 */
class Facebook extends LatestAndGreatest {
    /**
     * Define the cache filename
     * @var String
     */
    protected $cacheFileName = 'lag--facebook.json';

    /**
     * Initalise the app ID variable
     * @var String
     */
    protected $appId;

    /**
     * Initalise the app secret variable
     * @var String
     */
    protected $appSecret;

    /**
     * Initalise the API secret variable
     * @var String
     */
    protected $pageName;

    /**
     * Define the API end point
     * @var String
     */
    protected $endpoint = 'https://graph.facebook.com/';

    /**
     * This fires when instance created
     */
    public function __construct($options = []) {
        try {
            parent::__construct($options);
            $this->setAppKey();
            $this->setAppSecret();
            $this->setPageName();
            parent::init();
        } catch (Exception $e) {
            echo '<pre>';
            echo 'Message: ' . $e->getMessage(). PHP_EOL;
            echo 'File: ' . $e->getFile(). PHP_EOL;
            echo 'Line: ' . $e->getLine(). PHP_EOL;
            echo 'Trace:' . PHP_EOL . $e->getTraceAsString(). PHP_EOL;
            echo '</pre>';
        }
    }

    /**
     * Set the app key
     */
    public function setAppKey() {
        if (!getenv('FACEBOOK_APP_ID')) {
            trigger_error('No Facebook app ID defined in your .env', E_USER_ERROR);
        }

        $this->appId = getenv('FACEBOOK_APP_ID');
    }

    /**
     * Set the app secret
     */
    public function setAppSecret() {
        if (!getenv('FACEBOOK_APP_SECRET')) {
            trigger_error('No Facebook app secret defined in your .env', E_USER_ERROR);
        }

        $this->appSecret = getenv('FACEBOOK_APP_SECRET');
    }

    /**
     * Set the page name
     */
    public function setPageName() {
        if (!getenv('FACEBOOK_PAGE_NAME')) {
            trigger_error('No Facebook page name defined in your .env', E_USER_ERROR);
        }

        $this->pageName = getenv('FACEBOOK_PAGE_NAME');
    }

    /**
     * Get the likes endpoint
     * @return String
     */
    public function getPageLikesApiEndpoint() {
        $args = [
            'access_token' => $this->appId . '|' . $this->appSecret,
            'fields' => 'fan_count'
        ];

        return $this->endpoint . $this->pageName . '/?' . urldecode(http_build_query($args));
    }

    /**
     * Get the posts endpoint
     * @return String
     */
    public function getPostsApiEndpoint() {
        $args = [
            'access_token' => $this->appId . '|' . $this->appSecret,
            'fields' => 'posts'
        ];

        return $this->endpoint . $this->pageName . '/?' . urldecode(http_build_query($args));
    }

    /**
     * Get the attachment endpoint
     * @return String
     */
    public function getPostAttachmentApiEndpoint($postId) {
        $args = [
            'access_token' => $this->appId . '|' . $this->appSecret
        ];

        return $this->endpoint . $postId . '/attachments?' . urldecode(http_build_query($args));
    }

    /**
     * Get the post likes endpoint
     * @return String
     */
    public function getPostLikesApiEndpoint($postId) {
        $args = [
            'access_token' => $this->appId . '|' . $this->appSecret,
            'summary' => 'true'
        ];

        return $this->endpoint . $postId . '/likes?' . urldecode(http_build_query($args));
    }

    /**
     * Get the post comments endpoint
     * @return String
     */
    public function getPostCommentsApiEndpoint($postId) {
        $args = [
            'access_token' => $this->appId . '|' . $this->appSecret,
            'summary' => 'true'
        ];

        return $this->endpoint . $postId . '/comments?' . urldecode(http_build_query($args));
    }

    /**
     * Get page statistics
     * @return Array
     */
    public function getStatisticsArray() {
        // Get data from API
        $endpointResult = @file_get_contents($this->getPageLikesApiEndpoint());
        if (!$endpointResult) {
            throw new Exception('No data returned from endpoint');
        }

        // Convert to object
        $object = json_decode($endpointResult);

        // Create array
        $array = [
            'likes' => $object->fan_count
        ];

        return $array;
    }

    /**
     * Get posts
     * @return Array
     */
    public function getPostsArray() {
        // Get data from API
        $endpointResult = @file_get_contents($this->getPostsApiEndpoint());
        if (!$endpointResult) {
            throw new Exception('No data returned from endpoint');
        }

        // Convert to array
        $dataArray = json_decode($endpointResult, true);

        // Get posts
        $posts = $dataArray['posts']['data'];

        // Get actual message posts
        $messagePosts = [];
        foreach ($posts as $post) {
            if (!array_key_exists('message', $post)) {
                continue;
            }

            $messagePosts[] = $post;
        }

        // Shrink array
        $latestPosts = array_slice($messagePosts, 0, $this->maxResults);

        // Create usable data array
        $array = [];
        foreach ($latestPosts as $post) {

            $array[$post['id']] = [
                'id' => $post['id'],
                'text' => $post['message'],
                'date' => strtotime($post['created_time'])
            ];

            // Is there media attached to the post?
            $media = $this->getPostMediaArray($post['id']);
            if (isset($media['image'])) {
                $array[$post['id']]['media'] = [
                    'thumbnail' => $media['image']->src,
                    'width' => $media['image']->width,
                    'height' => $media['image']->height,
                ];
            }

            // Get post stats
            $array[$post['id']]['likes'] = $this->getPostLikesCount($post['id']);
            $array[$post['id']]['comments'] = $this->getPostCommentsCount($post['id']);
        }

        // Remove named keys
        $array = array_values($array);

        return $array;
    }

    /**
     * Get a posts media
     * @param  Integer $postId A post id
     * @return Array
     */
    public function getPostMediaArray($postId) {
        // Check for Post ID
        if (!$postId) {
            throw new Exception('No Post ID defined');
        }

        // Get data from API
        $endpointResult = @file_get_contents($this->getPostAttachmentApiEndpoint($postId));
        if (!$endpointResult) {
            throw new Exception('No data returned from endpoint');
        }

        // Convert to object
        $object = json_decode($endpointResult);

        // If no media, return empty array
        if (!isset($object->data[0]->media)) {
            return [];
        }

        // Return media
        return (array) $object->data[0]->media;
    }

    public function getPostLikesCount($postId) {
        // Check for Post ID
        if (!$postId) {
            throw new Exception('No Post ID defined');
        }

        // Get data from API
        $endpointResult = @file_get_contents($this->getPostLikesApiEndpoint($postId));
        if (!$endpointResult) {
            throw new Exception('No data returned from endpoint');
        }

        // Convert to object
        $object = json_decode($endpointResult);

        // If no media, return empty array
        if (!isset($object->summary->total_count)) {
            return [];
        }

        // Return media
        return (int) $object->summary->total_count;
    }

    public function getPostCommentsCount($postId) {
        // Check for Post ID
        if (!$postId) {
            throw new Exception('No Post ID defined');
        }

        // Get data from API
        $endpointResult = @file_get_contents($this->getPostCommentsApiEndpoint($postId));
        if (!$endpointResult) {
            throw new Exception('No data returned from endpoint');
        }

        // Convert to object
        $object = json_decode($endpointResult);

        // If no media, return empty array
        if (!isset($object->summary->total_count)) {
            return [];
        }

        // Return media
        return (int) $object->summary->total_count;
    }
}