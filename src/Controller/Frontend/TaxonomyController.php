<?php

declare(strict_types=1);

namespace Bolt\Controller\Frontend;

use Bolt\Configuration\Config;
use Bolt\Content\ContentType;
use Bolt\Controller\BaseController;
use Bolt\Entity\Content;
use Bolt\Repository\ContentRepository;
use Bolt\Repository\FieldRepository;
use Bolt\TemplateChooser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class TaxonomyController extends BaseController
{
    public function __construct(Config $config, CsrfTokenManagerInterface $csrfTokenManager, TemplateChooser $templateChooser)
    {
        parent::__construct($config, $csrfTokenManager);

        $this->templateChooser = $templateChooser;
    }

    /**
     * @Route("
     *     /{taxonomyslug}/{slug}",
     *     name="taxonomy",
     *     requirements={"taxonomyslug"="%bolt.requirement.taxonomies%"},
     *     methods={"GET"}
     * )
     */
    public function listing(ContentRepository $content, Request $request, string $taxonomyslug, string $slug): Response
    {
        $page = (int) $request->query->get('page', 1);

        /** @var Content[] $records */
        $records = $content->findForPage($page);

        $contenttype = ContentType::factory('page', $this->config->get('contenttypes'));

        $templates = $this->templateChooser->listing($contenttype);

        return $this->renderTemplate($templates, ['records' => $records]);
    }

    /**
     * @Route("/record/{slug}", methods={"GET"}, name="record")
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function record(ContentRepository $contentRepository, FieldRepository $fieldRepository, $slug = null): Response
    {
        if (! is_numeric($slug)) {
            $field = $fieldRepository->findOneBySlug($slug);
            $record = $field->getContent();
        } else {
            $record = $contentRepository->findOneBy(['id' => $slug]);
        }

        $recordSlug = $record->getDefinition()['singular_slug'];

        $context = [
            'record' => $record,
            $recordSlug => $record,
        ];

        $templates = $this->templateChooser->record($record);

        return $this->renderTemplate($templates, $context);
    }
}
