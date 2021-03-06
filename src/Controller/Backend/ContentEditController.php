<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend;

use Bolt\Controller\BaseController;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
use Carbon\Carbon;
use Doctrine\Common\Persistence\ObjectManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * Class ContentEditController.
 *
 * @Security("has_role('ROLE_ADMIN')")
 */
class ContentEditController extends BaseController
{
    /**
     * @Route("/edit/{id}", name="bolt_content_edit", methods={"GET"})
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function edit(string $id, Request $request, ?Content $content = null): Response
    {
        if (! $content) {
            $content = new Content();
            $content->setAuthor($this->getUser());
            $content->setContentType($id);
            $content->setConfig($this->config);
        }

        $twigvars = [
            'record' => $content,
            'locales' => $content->getLocales(),
            'currentlocale' => $this->getEditLocale($request, $content),
        ];

        return $this->renderTemplate('content/edit.html.twig', $twigvars);
    }

    /**
     * @Route("/edit/{id}", name="bolt_content_edit_post", methods={"POST"})
     */
    public function editPost(Request $request, ObjectManager $manager, UrlGeneratorInterface $urlGenerator, ?Content $content = null): Response
    {
        $token = new CsrfToken('editrecord', $request->request->get('_csrf_token'));

        if (! $this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }

        $content = $this->contentFromPost($content, $request);

        $manager->persist($content);
        $manager->flush();

        $this->addFlash('success', 'content.updated_successfully');

        $urlParams = [
            'id' => $content->getId(),
            'locale' => $this->getEditLocale($request, $content) ?: null,
        ];
        $url = $urlGenerator->generate('bolt_content_edit', $urlParams);

        return new RedirectResponse($url);
    }

    private function contentFromPost(?Content $content, Request $request): Content
    {
        $post = $request->request->all();

        $locale = $this->getPostedLocale($post);

        if (! $content) {
            $content = new Content();
            $content->setAuthor($this->getUser());
            $content->setContentType($request->attributes->get('id'));
            $content->setConfig($this->config);
        }

        $content->setStatus(current($post['status']));
        $content->setPublishedAt(new Carbon($post['publishedAt']));
        $content->setDepublishedAt(new Carbon($post['depublishedAt']));

        foreach ($post['fields'] as $key => $postfield) {
            $this->updateFieldFromPost($key, $postfield, $content, $locale);
        }

        return $content;
    }

    private function updateFieldFromPost(string $key, $postfield, Content $content, string $locale): void
    {
        if ($content->hasLocalisedField($key, $locale)) {
            $field = $content->getLocalisedField($key, $locale);
        } else {
            $fields = collect($content->getDefinition()->get('fields'));
            $field = Field::factory($fields->get($key), $key);
            $field->setName($key);
            $content->addField($field);
        }

        $field->setValue((array) $postfield);

        if ($field->getDefinition()->get('localise')) {
            $field->setLocale($locale);
        } else {
            $field->setLocale('');
        }
    }

    private function getEditLocale(Request $request, Content $content): string
    {
        $locale = $request->query->get('locale', '');
        $locales = $content->getLocales();

        if (! $locales->contains($locale)) {
            $locale = $locales->first();
        }

        return $locale;
    }

    private function getPostedLocale(array $post): string
    {
        return $post['_edit_locale'] ?: '';
    }
}
