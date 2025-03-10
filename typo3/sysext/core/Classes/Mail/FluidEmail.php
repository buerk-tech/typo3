<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Core\Mail;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\AbstractPart;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Information\Typo3Information;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3\CMS\Fluid\View\TemplatePaths;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;

/**
 * Send out templated HTML/plain text emails with Fluid.
 *
 * @todo: This construct needs an overhaul because it violates "Composition over inheritance".
 *        This is obvious when looking at __construct() already. FluidEmail extends symfony Email
 *        (which is a symfony Message), and thus has to deal with things it shouldn't at this point.
 *        The repeated calls to $this->resetBody() are the proof something is really wrong here.
 *        At first glance it looks as if something like this should happen: The class responsibility
 *        should be rendering of the subject and body only. It should probably be created using a factory,
 *        returning an instance of some interface, with the factory interface being injected to consumers.
 *        This would allow rendering emails with some different template engine, by injecting a different
 *        factory interface implementation that returns some different class. With subject and body being
 *        created by this, a symfony email should be created (maybe with a facade or factory again). And
 *        then hand the mail over to something that can send it.
 *        Working on this may go along with a renaming of the class structure, and we may want to
 *        ensure abstraction does not explode too much along the way ...
 */
class FluidEmail extends Email
{
    public const FORMAT_HTML = 'html';
    public const FORMAT_PLAIN = 'plain';
    public const FORMAT_BOTH = 'both';

    /**
     * @var string[]
     */
    protected array $format = ['html', 'plain'];
    protected string $templateName = 'Default';
    protected FluidViewAdapter $view;

    public function __construct(?TemplatePaths $templatePaths = null, ?Headers $headers = null, ?AbstractPart $body = null)
    {
        parent::__construct($headers, $body);
        $viewFactory = GeneralUtility::makeInstance(ViewFactoryInterface::class);
        $view = $viewFactory->create(new ViewFactoryData());
        if (!$view instanceof FluidViewAdapter) {
            throw new \RuntimeException(
                'Class FluidEmail can only deal with Fluid views via FluidViewAdapter',
                1724686399
            );
        }
        $this->view = $view;
        // @todo: This is where the problem starts: TemplatePaths() is hardcoded fluid, and part of the
        //        current FluidEmail API. We can not put this into ViewFactoryData() directly. While
        //        we *could* unpack the paths and format to an array again, we should probably better
        //        redesign this implementation and work on the main comment above along the way.
        //        Also note methods like getViewHelperVariableContainer() are hard-bound to fluid, too.
        if ($templatePaths === null) {
            $templatePaths = new TemplatePaths();
            $templatePaths->setTemplateRootPaths($GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'] ?? []);
            $templatePaths->setLayoutRootPaths($GLOBALS['TYPO3_CONF_VARS']['MAIL']['layoutRootPaths'] ?? []);
            $templatePaths->setPartialRootPaths($GLOBALS['TYPO3_CONF_VARS']['MAIL']['partialRootPaths'] ?? []);
        }

        $this->view->getRenderingContext()->setTemplatePaths($templatePaths);
        $this->view->assignMultiple($this->getDefaultVariables());
        $this->format($GLOBALS['TYPO3_CONF_VARS']['MAIL']['format'] ?? self::FORMAT_BOTH);
    }

    public function format(string $format): static
    {
        $this->format = match ($format) {
            self::FORMAT_BOTH => [self::FORMAT_HTML, self::FORMAT_PLAIN],
            self::FORMAT_HTML => [self::FORMAT_HTML],
            self::FORMAT_PLAIN => [self::FORMAT_PLAIN],
            default => throw new \InvalidArgumentException('Setting FluidEmail->format() must be either "html", "plain" or "both", no other formats are currently supported', 1580743847),
        };
        $this->resetBody();
        return $this;
    }

    public function setTemplate(string $templateName): static
    {
        $this->templateName = $templateName;
        $this->resetBody();
        return $this;
    }

    public function assign($key, $value): static
    {
        $this->view->assign($key, $value);
        $this->resetBody();
        return $this;
    }

    public function assignMultiple(array $values): static
    {
        $this->view->assignMultiple($values);
        $this->resetBody();
        return $this;
    }

    /*
     * Shorthand setters
     */
    public function setRequest(ServerRequestInterface $request): static
    {
        $this->view->getRenderingContext()->setAttribute(ServerRequestInterface::class, $request);
        $this->view->assign('request', $request);
        if ($request->getAttribute('normalizedParams') instanceof NormalizedParams) {
            $this->view->assign('normalizedParams', $request->getAttribute('normalizedParams'));
        } else {
            $this->view->assign('normalizedParams', NormalizedParams::createFromServerParams($_SERVER));
        }
        $this->resetBody();
        return $this;
    }

    protected function getDefaultVariables(): array
    {
        return [
            'typo3' => [
                'sitename' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'],
                'formats' => [
                    'date' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'],
                    'time' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'],
                ],
                'systemConfiguration' => $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'],
                'information' => GeneralUtility::makeInstance(Typo3Information::class),
            ],
        ];
    }

    public function ensureValidity(): void
    {
        $this->generateTemplatedBody();
        parent::ensureValidity();
    }

    public function getBody(): AbstractPart
    {
        $this->generateTemplatedBody();
        return parent::getBody();
    }

    /**
     * @return resource|string|null
     */
    public function getHtmlBody(bool $forceBodyGeneration = false)
    {
        if ($forceBodyGeneration) {
            $this->generateTemplatedBody('html');
        } elseif (parent::getHtmlBody() === null) {
            $this->generateTemplatedBody();
        }
        return parent::getHtmlBody();
    }

    /**
     * @return resource|string|null
     */
    public function getTextBody(bool $forceBodyGeneration = false)
    {
        if ($forceBodyGeneration) {
            $this->generateTemplatedBody('plain');
        } elseif (parent::getTextBody() === null) {
            $this->generateTemplatedBody();
        }
        return parent::getTextBody();
    }

    /**
     * @internal Only used for ext:form, not part of TYPO3 Core API.
     */
    public function getViewHelperVariableContainer(): ViewHelperVariableContainer
    {
        // the variables are possibly modified in ext:form, so content must be rendered
        $this->resetBody();
        return $this->view->getRenderingContext()->getViewHelperVariableContainer();
    }

    protected function generateTemplatedBody(string $forceFormat = ''): void
    {
        // Use a local variable to allow forcing a specific format
        $format = $forceFormat ? [$forceFormat] : $this->format;

        $tryToRenderSubjectSection = false;
        if (in_array(static::FORMAT_HTML, $format, true) && ($forceFormat || parent::getHtmlBody() === null)) {
            $this->html($this->renderContent('html'));
            $tryToRenderSubjectSection = true;
        }
        if (in_array(static::FORMAT_PLAIN, $format, true) && ($forceFormat || parent::getTextBody() === null)) {
            $this->text(trim($this->renderContent('txt')));
            $tryToRenderSubjectSection = true;
        }

        if ($tryToRenderSubjectSection) {
            $subjectFromTemplate = $this->view->renderSection(
                'Subject',
                $this->view->getRenderingContext()->getVariableProvider()->getAll(),
                true
            );
            if (!empty($subjectFromTemplate)) {
                $this->subject($subjectFromTemplate);
            }
        }
    }

    protected function renderContent(string $format): string
    {
        $this->view->getRenderingContext()->getTemplatePaths()->setFormat($format);
        return $this->view->render($this->templateName);
    }

    protected function resetBody(): void
    {
        $this->html(null);
        $this->text(null);
    }
}
