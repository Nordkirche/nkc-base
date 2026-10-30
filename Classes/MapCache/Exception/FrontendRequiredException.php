<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\MapCache\Exception;

/**
 * Thrown by a map marker provider if the markers can only be built with the TypoScript of
 * the page (e.g. settings resolved from the rootline). The warm-up command skips the content
 * element, the first visit of the page builds the markers.
 */
final class FrontendRequiredException extends \RuntimeException {}
