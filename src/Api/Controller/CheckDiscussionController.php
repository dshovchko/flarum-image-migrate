<?php

namespace Dshovchko\ImageMigrate\Api\Controller;

use Dshovchko\ImageMigrate\Service\ImageChecker;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CheckDiscussionController implements RequestHandlerInterface
{
    protected $checker;

    public function __construct(ImageChecker $checker)
    {
        $this->checker = $checker;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $id = array_get($request->getQueryParams(), 'id');
        $discussion = Discussion::findOrFail($id);

        $externalImages = $this->checker->checkDiscussion($discussion);

        return new JsonResponse([
            'data' => [
                'count' => count($externalImages),
                'images' => $externalImages,
            ],
        ]);
    }
}
