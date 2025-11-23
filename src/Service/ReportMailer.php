<?php

namespace Dshovchko\ImageMigrate\Service;

use Flarum\Foundation\Config;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;

class ReportMailer
{
    protected $mailer;
    protected $settings;
    protected $config;

    public function __construct(Mailer $mailer, SettingsRepositoryInterface $settings, Config $config)
    {
        $this->mailer = $mailer;
        $this->settings = $settings;
        $this->config = $config;
    }

    public function sendReport(array $externalImages, ?string $mailto = null): void
    {
        $recipients = $mailto ? array_filter(array_map('trim', explode(',', $mailto))) : $this->getRecipients();
        
        if (empty($recipients)) {
            return;
        }

        $body = $this->buildReportBody($externalImages);
        $subject = $this->buildSubject($externalImages);

        foreach ($recipients as $recipient) {
            $this->mailer->raw($body, function (Message $message) use ($recipient, $subject) {
                $message->to($recipient)
                    ->subject($subject);
            });
        }
    }

    protected function getRecipients(): array
    {
        $recipients = $this->settings->get('dshovchko-image-migrate.scheduled_emails', '');
        return array_filter(array_map('trim', explode(',', $recipients)));
    }

    protected function buildSubject(array $externalImages): string
    {
        $count = count($externalImages);
        $date = (new \DateTime())->format('Y-m-d');
        return sprintf('External images report - %s (%d images)', $date, $count);
    }

    protected function buildReportBody(array $externalImages): string
    {
        $forumUrl = rtrim((string) $this->config->url(), '/');
        
        // Group by discussion
        $byDiscussion = [];
        foreach ($externalImages as $item) {
            $discussionId = $item['discussion_id'];
            if (!isset($byDiscussion[$discussionId])) {
                $byDiscussion[$discussionId] = [];
            }
            $byDiscussion[$discussionId][] = $item;
        }
        
        $totalImages = count($externalImages);
        $withIssues = count($byDiscussion);
        
        $body = "External Images Report\n\n";
        $body .= "Total external images: {$totalImages}\n";
        $body .= "⚠️ Discussions with issues: {$withIssues}\n";
        $body .= str_repeat('=', 50) . "\n\n";
        
        foreach ($byDiscussion as $discussionId => $items) {
            $imageCount = count($items);
            
            $body .= "\n⚠️ Discussion {$discussionId}\n";
            $body .= "{$forumUrl}/d/{$discussionId}\n";
            $body .= " external images in posts ({$imageCount})\n";
            
            // Group by post
            $byPost = [];
            foreach ($items as $item) {
                $postId = $item['post_id'];
                if (!isset($byPost[$postId])) {
                    $byPost[$postId] = [
                        'number' => $item['post_number'],
                        'urls' => []
                    ];
                }
                $byPost[$postId]['urls'][] = $item['image_url'];
            }
            
            $postIds = array_keys($byPost);
            $body .= " posts: " . implode(' ', $postIds) . "\n";
            
            foreach ($byPost as $postId => $data) {
                $body .= "  Post #{$postId}: " . count($data['urls']) . " image(s)\n";
                $body .= "    {$forumUrl}/d/{$discussionId}/{$data['number']}\n";
                foreach ($data['urls'] as $url) {
                    $body .= "    - {$url}\n";
                }
            }
            $body .= "\n";
        }

        return $body;
    }
}
