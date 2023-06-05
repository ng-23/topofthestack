<?php

declare(strict_types=1);
require_once realpath(dirname(__FILE__) . '../../../') . '/mappers/BlogMapper.php';


/**
 * Service for obtaining a list of the blog_ids of the top 10 trending Blogs
 */
class GetTrendingBlogs {

    /**
     * Calculated primarily on views, likes, and comments per hour
     * See: https://stackoverflow.com/questions/19197607/count-record-views-for-a-time-period
     * So Blogs would have columns for total_views and total_likes
     * Then it would have columns for views_in_day and likes_in_day
     * After 24 hours, these columns would be reset to 0
     * 
     * I'd have an event that, every 24 hours, would reset the views_in_day and likes_in_day columns to 0
     * I'd have a trigger for the total_views and total_likes columns so that an update to either would be automatically reflected in
     * views_in_day and likes_in_day columns
     * So if I view a blog, the web app code updates the total_views column, and the mysql trigger updates the views_in_day column
     * Then, after 24 hours, the mysql event resets views_in_day to 0, while total_views stays the same
     * 
     */

    private BlogMapper $mapper;

    private array $trending_blogs;

    public function __construct(BlogMapper $mapper) {
        $this->mapper = $mapper;   
    }

    public function calcTrendiness(PublishedBlog $blog) {
        // y = trendiness; v = views today; c = comments today; l = likes today
        // y = (v+.5l) + (c^1.5)

        $views_today = $blog->getViewsToday();
        $comments_today = $blog->getCommentsToday();
        $likes_today = $blog->getLikesToday();

        $trendiness = ($views_today+($likes_today/2)) + ($comments_today**1.5);

        return $trendiness;
    }

    public function chooseTrending(int $amount = 1) {
        if($amount < 0) {
            throw new Exception();
        }



        $most_viewed_today = $this->mapper->fetchByViews($when = "today", $amount);

        $trendiness_scores = [];

        foreach($most_viewed_today as $blog) {
            $trendiness_scores[$blog] = $this->calcTrendiness($blog);
        }





    }

    public function getBlogs() {

    }



}