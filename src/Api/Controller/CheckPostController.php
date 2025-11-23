<?php

namespace Dshovchko\ImageMigrate\Api\Controller;

use Dshovchko\ImageMigrate\Service\ImageChecker;
use Flarum\Http\RequestUtil;
use Flarum\Post\Post;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CheckPostController implements RequestHandlerInterface
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

        $id = $request->getAttribute('id');
        $post = Post::findOrFail($id);

        $externalImages = $this->checker->checkPost($post);

        return new JsonResponse([
            'data' => [
                'count' => count($externalImages),
                'images' => $externalImages,
            ],
        ]);
    }
}
