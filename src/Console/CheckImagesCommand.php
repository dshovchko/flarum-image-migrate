<?php

namespace Dshovchko\ImageMigrate\Console;

use Dshovchko\ImageMigrate\Service\ImageChecker;
use Dshovchko\ImageMigrate\Service\ImageMigrator;
use Dshovchko\ImageMigrate\Service\ReportMailer;
use Dshovchko\ImageMigrate\SnapGrab\SnapGrabClient;
use Dshovchko\ImageMigrate\SnapGrab\SnapGrabException;
use Flarum\Console\AbstractCommand;
use Flarum\Discussion\Discussion;
use Flarum\Post\CommentPost;
use Symfony\Component\Console\Input\InputOption;

class CheckImagesCommand extends AbstractCommand
{
    protected $checker;
    protected $mailer;
    protected $migrator;
    protected $snapGrabClient;

    public function __construct(ImageChecker $checker, ReportMailer $mailer, ImageMigrator $migrator, SnapGrabClient $snapGrabClient)
    {
        parent::__construct();
        $this->checker = $checker;
        $this->mailer = $mailer;
        $this->migrator = $migrator;
        $this->snapGrabClient = $snapGrabClient;
    }

    protected function configure()
    {
        $this->setName('image-migrate:check')
             ->setDescription('Check posts for external images')
             ->addOption('discussion', null, InputOption::VALUE_REQUIRED, 'Process only discussion with the specified ID')
             ->addOption('all', null, InputOption::VALUE_NONE, 'Process all discussions')
             ->addOption('post', null, InputOption::VALUE_REQUIRED, 'Process only comment post with the specified ID')
             ->addOption('mailto', null, InputOption::VALUE_REQUIRED, 'Send the checking log to the specified email')
             ->addOption('fix', null, InputOption::VALUE_NONE, 'Migrate external images to SnapGrab storage');
    }

    protected function fire()
    {
        $discussionId = $this->input->getOption('discussion');
        $postId = $this->input->getOption('post');
        $all = $this->input->getOption('all');
        $mailto = $this->input->getOption('mailto');
        $fix = $this->input->getOption('fix');

        if (!$postId && !$discussionId && !$all) {
            $this->error('Please specify one of: --discussion=<id>, --post=<id>, or --all');
            return 1;
        }

        if (($postId && $all) || ($discussionId && $all) || ($postId && $discussionId)) {
            $this->error('Please specify a single scope: --discussion, --post, or --all.');
            return 1;
        }

        $externalImages = [];

        if ($postId) {
            $externalImages = $this->checkPost((int) $postId);
        } elseif ($discussionId) {
            $externalImages = $this->checkDiscussion((int) $discussionId);
        } elseif ($all) {
            $externalImages = $this->checkAll();
        }

        if ($fix) {
            return $this->runFix($externalImages);
        }

        $this->displayResults($externalImages, $mailto);
    }

    protected function checkPost(int $postId): array
    {
        $this->info("Checking post #{$postId}...");
        
        $post = CommentPost::find($postId);
        if (!$post) {
            $this->error("Post #{$postId} not found");
            return [];
        }

        return $this->checker->checkPost($post);
    }

    protected function checkDiscussion(int $discussionId): array
    {
        $this->info("Checking discussion #{$discussionId}...");
        
        $discussion = Discussion::find($discussionId);
        if (!$discussion) {
            $this->error("Discussion #{$discussionId} not found");
            return [];
        }

        return $this->checker->checkDiscussion($discussion);
    }

    protected function checkAll(): array
    {
        $this->info('Checking all posts for external images...');
        
        return $this->checker->checkAllPosts();
    }

    protected function displayResults(array $externalImages, ?string $mailto): void
    {
        $count = count($externalImages);
        
        if ($count > 0) {
            // Group by discussion
            $byDiscussion = [];
            foreach ($externalImages as $item) {
                $discussionId = $item['discussion_id'];
                if (!isset($byDiscussion[$discussionId])) {
                    $byDiscussion[$discussionId] = [];
                }
                $byDiscussion[$discussionId][] = $item;
            }
            
            $this->info("Found {$count} external image(s) in " . count($byDiscussion) . " discussion(s)\n");
            
            foreach ($byDiscussion as $discussionId => $items) {
                $this->info("Discussion #{$discussionId}:");
                
                // Group by post
                $byPost = [];
                foreach ($items as $item) {
                    $postId = $item['post_id'];
                    if (!isset($byPost[$postId])) {
                        $byPost[$postId] = [];
                    }
                    $byPost[$postId][] = $item['image_url'];
                }
                
                foreach ($byPost as $postId => $urls) {
                    $this->info("  Post #{$postId}: " . count($urls) . " image(s)");
                    foreach ($urls as $url) {
                        $this->info("    - {$url}");
                    }
                }
                $this->info("");
            }

            if ($mailto) {
                $this->mailer->sendReport($externalImages, $mailto);
                $this->info("Report sent to {$mailto}");
            }
        } else {
            $this->info('No external images found');
        }
    }

    protected function runFix(array $externalImages): int
    {
        if (empty($externalImages)) {
            $this->info('No external images found. Nothing to migrate.');
            return 0;
        }

        try {
            $this->snapGrabClient->ensureConfigured();
            $this->snapGrabClient->healthCheck();
        } catch (SnapGrabException $e) {
            $this->error('Health check failed: '.$e->getMessage());
            return 1;
        }

        $grouped = [];
        foreach ($externalImages as $image) {
            $grouped[$image['post_id']][] = $image;
        }

        $totalPosts = count($grouped);
        $totalImages = count($externalImages);
        $this->info(sprintf('Migrating %d image(s) across %d post(s)...', $totalImages, $totalPosts));

        foreach ($grouped as $postId => $images) {
            $post = CommentPost::find($postId);
            if (!$post) {
                $this->error("Post #{$postId} no longer exists");
                return 1;
            }

            $this->info(sprintf('  • Post #%d (%d image%s)', $postId, count($images), count($images) === 1 ? '' : 's'));

            try {
                $this->migrator->migrate($post, $images);
            } catch (SnapGrabException $e) {
                $this->error('Migration failed: '.$e->getMessage());
                return 1;
            }
        }

        $this->info('Migration completed successfully.');

        return 0;
    }
}
