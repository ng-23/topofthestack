<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/drafts/BlogDraft.php';

class DraftMapper extends DataMapper {
    private TagMapper $tag_mapper;

    public function __construct(PDO $db_connection, TagMapper $tag_mapper) {
        $this->db_connection = $db_connection;
        $this->tag_mapper = $tag_mapper;
    }

    public function draftFromData(array $draft_data): BlogDraft {
        $draft = new BlogDraft($draft_data["drafter_id"], $draft_data["draft_uri"],
        $draft_data["name"], $draft_data["draft_id"]);

        $draft->setPublishedBlogId($draft_data["published_blog_id"]);

        $draft->setTitle($draft_data["title"]);

        foreach($draft_data["tags"] as $tag) {
            $draft->addTag($tag->getName());
        }

        $draft->setCreatedAt(DateTimeImmutable::createFromFormat(BlogDraft::DATE_FORMAT, $draft_data["created_at"]));
        $draft->setUpdatedAt(DateTimeImmutable::createFromFormat(BlogDraft::DATE_FORMAT, $draft_data["updated_at"]));

        return $draft;
    }

    public function draftsFromData(array $drafts_data): array {
        $drafts = [];

        foreach($drafts_data as $draft_data) {
            $draft_data = $this->pushTags($draft_data);

            $draft = $this->draftFromData($draft_data);

            array_push($drafts, $draft);
        }

        return $drafts;
    }

    private function pushTags(array $draft_data): array {
        $tags = $this->tag_mapper->fetchByDraftId($draft_data["draft_id"]);

        $draft_data["tags"] = $tags;

        return $draft_data;
    }

    public function fetchById(int $draft_id): ?BlogDraft {
        $draft = NULL;

        $query = "SELECT `draft_id`, `drafter_id`, `body_uri`, `published_blog_id`, 
                `name`, `title`, `created_at`, `updated_at` FROM blog_drafts
                WHERE `draft_id` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $draft_id);
        $stmt->execute();

        if($stmt->rowCount() == 1) {
            $draft_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $draft_data = $this->pushTags($draft_data);

            $draft = $this->draftFromData($draft_data);
        }

        return $draft;
    }

    public function fetchByDrafterId(int $drafter_id, int $amount = 1, int $offset = 0) {
        if($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $drafts = [];

        $query = "SELECT `draft_id`, `drafter_id`, `body_uri`, `published_blog_id`, 
                `name`, `title`, `created_at`, `updated_at` FROM blog_drafts
                WHERE drafter_id=? LIMIT ? OFFSET ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $drafter_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        if($stmt->rowCount() >= 1) {
            $drafts_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $drafts = $this->draftsFromData($drafts_data);
        }

        return $drafts;
    }

    /**
     * Returns the draft(s) that are associated with a particular published blog
     */
    public function fetchByPublishedBlogId(int $published_blog_id, int $amount = 1, int $offset = 0) {
        if($amount < 0 || $offset < 0) {
            throw new Exception();
        }
        
        $drafts = [];

        $query = "SELECT `draft_id`, `drafter_id`, `body_uri`, `published_blog_id`, 
                `name`, `title`, `created_at`, `updated_at` FROM blog_drafts
                WHERE published_blog_id=? LIMIT ? OFFSET ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $published_blog_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        if($stmt->rowCount() >= 1) {
            $drafts_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $drafts = $this->draftsFromData($drafts_data);
        }
        return $drafts;
    }

}