<?php

namespace Dshovchko\ImageMigrate\Console;

use Dshovchko\ImageMigrate\Service\ImageChecker;
use Dshovchko\ImageMigrate\Service\ReportMailer;
use Flarum\Console\AbstractCommand;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Symfony\Component\Console\Input\InputOption;

class CheckImagesCommand extends AbstractCommand
{
    protected $checker;
    protected $mailer;

    public function __construct(ImageChecker $checker, ReportMailer $mailer)
    {
        parent::__construct();
        $this->checker = $checker;
        $this->mailer = $mailer;
    }

    protected function configure()
    {
        $this->setName('image-migrate:check')
             ->setDescription('Check posts for external images')
             ->addOption('discussion', null, InputOption::VALUE_REQUIRED, 'Process only discussion with the specified ID')
             ->addOption('all', null, InputOption::VALUE_NONE, 'Process all discussions')
             ->addOption('post', null, InputOption::VALUE_REQUIRED, 'Process only comment post with the specified ID')
             ->addOption('mailto', null, InputOption::VALUE_REQUIRED, 'Send the checking log to the specified email')
             ->addOption('fix', null, InputOption::VALUE_NONE, 'Migrate external images to local storage (TODO: will be implemented in next minor version)');
    }

    protected function fire()
    {
        $discussionId = $this->input->getOption('discussion');
        $postId = $this->input->getOption('post');
        $all = $this->input->getOption('all');
        $mailto = $this->input->getOption('mailto');
        $fix = $this->input->getOption('fix');

        // TODO: Implement --fix option to migrate external images to local storage
        if ($fix) {
            $this->error('The --fix option is not yet implemented. It will be available in the next minor version.');
            return;
        }

        if ($postId) {
            $this->checkPost($postId, $mailto);
        } elseif ($discussionId) {
            $this->checkDiscussion($discussionId, $mailto);
        } elseif ($all) {
            $this->checkAll($mailto);
        } else {
            $this->error('Please specify one of: --discussion=<id>, --post=<id>, or --all');
            return 1;
        }
    }

    protected function checkPost(int $postId, ?string $mailto): void
    {
        $this->info("Checking post #{$postId}...");
        
        $post = Post::find($postId);
        if (!$post) {
            $this->error("Post #{$postId} not found");
            return;
        }

        $externalImages = $this->checker->checkPost($post);
        $this->displayResults($externalImages, $mailto);
    }

    protected function checkDiscussion(int $discussionId, ?string $mailto): void
    {
        $this->info("Checking discussion #{$discussionId}...");
        
        $discussion = Discussion::find($discussionId);
        if (!$discussion) {
            $this->error("Discussion #{$discussionId} not found");
            return;
        }

        $externalImages = $this->checker->checkDiscussion($discussion);
        $this->displayResults($externalImages, $mailto);
    }

    protected function checkAll(?string $mailto): void
    {
        $this->info('Checking all posts for external images...');
        
        $externalImages = $this->checker->checkAllPosts();
        $this->displayResults($externalImages, $mailto);
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
}
