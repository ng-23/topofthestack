<?php

declare(strict_types=1);

namespace tots\Services\Blogs;

use DateTimeImmutable;
use tots\Mappers\BlogMapper;

class SearchBlogsService {
    private BlogMapper $blog_mapper;

    private array $blogs;

    public function __construct(BlogMapper $blog_mapper)
    {
        $this->blog_mapper = $blog_mapper;
        $this->blogs = [];
    }

    public function searchById(int $blog_id) {
        $this->blogs = [$this->blog_mapper->fetchById($blog_id)];
    }

    public function searchByAuthor(int $author_id, int $amount, int $offset) {
        $this->blogs = $this->blog_mapper->fetchByAuthor($author_id, $amount, $offset);
    }

    public function searchByTitle(String $title, int $amount, int $offset, bool $exact_match = false) {
        $this->blogs = $this->blog_mapper->fetchByTitle($title, $amount, $offset, $exact_match);
    }

    public function searchByAuthorAndTitle(int $author_id, String $title, bool $exact_match = false) {
        $this->blogs = $this->blog_mapper->fetchByAuthorAndTitle($author_id, $title, $exact_match);
    }

    public function searchByAuthorAndCreatedOn(int $author_id, DateTimeImmutable $date, int $amount, int $offset) {
        $this->blogs = $this->blog_mapper->fetchByAuthorAndCreatedOn($author_id, $date, $amount, $offset);
    }

    public function searchByAuthorAndCreatedBetween(int $author_id, DateTimeImmutable $date1, DateTimeImmutable $date2, int $amount, int $offset) {
        $this->blogs = $this->blog_mapper->fetchByAuthorAndCreatedBetween($author_id, $date1, $date2, $amount, $offset);
    }

    public function searchByTags(array $tag_names, $amount, $offset) {
        $this->blogs = $this->blog_mapper->fetchByTagNames($tag_names, $amount, $offset);
    }

    public function getBlogs() {
        return $this->blogs;
    }
}