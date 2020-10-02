<?php

declare(strict_types=1);

namespace ComposerRequireChecker\NodeVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use UnexpectedValueException;

use function array_keys;
use function get_class;
use function sprintf;

final class DefinedSymbolCollector extends NodeVisitorAbstract
{
    /** @var mixed[] */
    private array $definedSymbols = [];

    public function __construct()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function beforeTraverse(array $nodes)
    {
        $this->definedSymbols = [];

        return parent::beforeTraverse($nodes);
    }

    /**
     * @return string[]
     */
    public function getDefinedSymbols(): array
    {
        return array_keys($this->definedSymbols);
    }

    public function enterNode(Node $node): Node
    {
        $this->recordClassDefinition($node);
        $this->recordInterfaceDefinition($node);
        $this->recordTraitDefinition($node);
        $this->recordFunctionDefinition($node);
        $this->recordConstDefinition($node);
        $this->recordDefinedConstDefinition($node);

        return $node;
    }

    private function recordClassDefinition(Node $node): void
    {
        if (! ($node instanceof Node\Stmt\Class_) || $node->isAnonymous()) {
            return;
        }

        $this->recordDefinitionOf($node);
    }

    private function recordInterfaceDefinition(Node $node): void
    {
        if (! ($node instanceof Node\Stmt\Interface_)) {
            return;
        }

        $this->recordDefinitionOf($node);
    }

    private function recordTraitDefinition(Node $node): void
    {
        if (! ($node instanceof Node\Stmt\Trait_)) {
            return;
        }

        $this->recordDefinitionOf($node);
    }

    private function recordFunctionDefinition(Node $node): void
    {
        if (! ($node instanceof Node\Stmt\Function_)) {
            return;
        }

        $this->recordDefinitionOf($node);
    }

    private function recordConstDefinition(Node $node): void
    {
        if (! ($node instanceof Node\Stmt\Const_)) {
            return;
        }

        foreach ($node->consts as $const) {
            $this->recordDefinitionOf($const);
        }
    }

    private function recordDefinedConstDefinition(Node $node): void
    {
        if (
            ! ($node instanceof Node\Expr\FuncCall)
            || ! ($node->name instanceof Node\Name)
            || $node->name->toString() !== 'define'
        ) {
            return;
        }

        if (
            $node->name->hasAttribute('namespacedName')
            && $node->name->getAttribute('namespacedName') instanceof Node\Name\FullyQualified
            && $node->name->getAttribute('namespacedName')->toString() !== 'define'
        ) {
            return;
        }

        if (! ($node->args[0]->value instanceof Node\Scalar\String_)) {
            return;
        }

        $this->recordDefinitionOfStringSymbol($node->args[0]->value->value);
    }

    private function recordDefinitionOf(Node $node): void
    {
        if (! isset($node->namespacedName)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Given node of type "%s" (defined at line %s)does not have an assigned "namespacedName" property: '
                    . 'did you pass it through a name resolver visitor?',
                    get_class($node),
                    $node->getLine()
                )
            );
        }

        $this->recordDefinitionOfStringSymbol((string) $node->namespacedName);
    }

    private function recordDefinitionOfStringSymbol(string $symbolName): void
    {
        $this->definedSymbols[$symbolName] = $symbolName;
    }
}
