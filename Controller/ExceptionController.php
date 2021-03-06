<?php

namespace FOS\RestBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference,
    Symfony\Component\DependencyInjection\ContainerAware,
    Symfony\Component\HttpKernel\Exception\FlattenException,
    Symfony\Component\HttpKernel\Log\DebugLoggerInterface,
    Symfony\Component\HttpFoundation\Response;

use FOS\RestBundle\Response\Codes;

/*
 * This file is part of the FOSRestBundle
 *
 * (c) Lukas Kahwe Smith <smith@pooteeweet.org>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Bulat Shakirzyanov <mallluhuct@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Custom ExceptionController that uses the view layer and supports HTTP response status code mapping
 */
class ExceptionController extends ContainerAware
{
    /**
     * Converts an Exception to a Response.
     *
     * @param FlattenException     $exception   A FlattenException instance
     * @param DebugLoggerInterface $logger      A DebugLoggerInterface instance
     * @param string               $format      The format to use for rendering (html, xml, ...)
     * @param integer              $code        An HTTP response code
     * @param string               $message     An HTTP response status message
     * @param array                $headers     HTTP response headers
     *
     * @return Response                         Response instance
     */
    public function showAction(FlattenException $exception, DebugLoggerInterface $logger = null, $format = 'html')
    {
        // the count variable avoids an infinite loop on
        // some Windows configurations where ob_get_level()
        // never reaches 0
        $count = 100;
        $currentContent = '';
        while (ob_get_level() && --$count) {
            $currentContent .= ob_get_clean();
        }

        $format = $this->getFormat($format);
        $code = $this->getStatusCode($exception);
        $parameters = $this->getParameters($currentContent, $code, $exception, $logger, $format);

        try {
            $view = $this->container->get('fos_rest.view');

            $view->setFormat($format);
            $view->setTemplate($this->getTemplate($format));
            $view->setParameters($parameters);
            $view->setStatusCode($code);

            $response = $view->handle();
        } catch (\Exception $e) {
            $message = $this->container->get('kernel')->isDebug() ? $e->getMessage() : 'Internal Server Error';
            $response = new Response($message, Codes::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response->headers->replace($exception->getHeaders());

        return $response;
    }

    /**
     * Extract the exception message
     *
     * @param FlattenException     $exception   A FlattenException instance
     *
     * @return string                           Message
     */
    protected function getExceptionMessage($exception)
    {
        $exceptionClass = $exception->getClass();
        $exceptionMap = $this->container->getParameter('fos_rest.exception.messages');

        return empty($exceptionMap[$exceptionClass]) ? '' : $exception->getMessage();
    }

    /**
     * Determine the status code to use for the response
     *
     * @param FlattenException     $exception   A FlattenException instance
     *
     * @return integer                          An HTTP response code
     */
    protected function getStatusCode($exception)
    {
        $exceptionClass = $exception->getClass();
        $exceptionMap = $this->container->getParameter('fos_rest.exception.codes');

        return isset($exceptionMap[$exceptionClass]) ? $exceptionMap[$exceptionClass] : $exception->getStatusCode();
    }

    /**
     * Determine the format to use for the response
     *
     * @param string               $format    The format to use for rendering (html, xml, ...)
     *
     * @return string                         Encoding format
     */
    protected function getFormat($format)
    {
        return $format;
    }

    /**
     * Determine the template to use for the response
     *
     * @param string               $format    The format to use for rendering (html, xml, ...)
     *
     * @return TemplateReference              Template reference
     */
    protected function getTemplate($format)
    {
        if ($this->container->get('kernel')->isDebug()) {
            $name = 'html' === $format ? 'exception_full' : 'exception';
        } else {
            $name = 'error';
        }

        return new TemplateReference('FrameworkBundle', 'Exception', $name, $format);
    }

    /**
     * Determine the parameters to pass to the view layer
     *
     * @param string               $currentContent  The current content in the output buffer
     * @param integer              $code            An HTTP response code
     * @param FlattenException     $exception       A FlattenException instance
     * @param DebugLoggerInterface $logger          A DebugLoggerInterface instance
     * @param string               $format          The format to use for rendering (html, xml, ...)
     *
     * @return array                                Template parameters
     */
    protected function getParameters($currentContent, $code, FlattenException $exception, DebugLoggerInterface $logger = null, $format = 'html')
    {
        $parameters  = array(
            'status' => 'error',
            'status_code' => $code,
            'status_text' => Response::$statusTexts[$code],
            'currentContent' => $currentContent,
            'message' => $this->getExceptionMessage($exception),
        );

        if ($format === 'html') {
            $parameters['exception'] = $exception;
            $parameters['logger'] = $logger;
        }

        return $parameters;
    }
}
