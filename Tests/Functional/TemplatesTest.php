<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;

/**
 * All Fluid files can be parsed (unknown view helpers and arguments fail), and
 * array arguments get no plain text such as configuration="" (Fluid would only
 * notice this when the template is rendered on a page).
 */
final class TemplatesTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['nordkirche/nkc-base'];

    public static function templateDataProvider(): \Generator
    {
        $base = dirname(__DIR__, 2) . '/Resources/Private/';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() === 'html') {
                yield substr($file->getPathname(), strlen($base)) => [$file->getPathname()];
            }
        }
    }

    #[Test]
    #[DataProvider('templateDataProvider')]
    public function templateCanBeParsedAndHasValidArguments(string $file): void
    {
        $renderingContext = $this->get(RenderingContextFactory::class)->create();

        $parsedTemplate = $renderingContext->getTemplateParser()->parse((string)file_get_contents($file), 'test_' . md5($file));

        $errors = [];
        $this->checkArguments($parsedTemplate->getRootNode(), $errors);
        self::assertSame([], $errors);
    }

    /**
     * @param string[] $errors
     */
    private function checkArguments(NodeInterface $node, array &$errors): void
    {
        if ($node instanceof ViewHelperNode) {
            $definitions = $node->getArgumentDefinitions();
            foreach ($node->getArguments() as $name => $argument) {
                $type = isset($definitions[$name]) ? $definitions[$name]->getType() : '';
                if ($argument instanceof TextNode && in_array($type, ['array', 'int', 'integer', 'float'], true)
                    && !($type !== 'array' && is_numeric($argument->getText()))
                ) {
                    $errors[] = sprintf('%s: argument "%s" expects %s, got text "%s"', $node->getViewHelperClassName(), $name, $type, $argument->getText());
                }
                if ($argument instanceof NodeInterface) {
                    $this->checkArguments($argument, $errors);
                }
            }
        }
        foreach ($node->getChildNodes() as $childNode) {
            $this->checkArguments($childNode, $errors);
        }
    }
}
