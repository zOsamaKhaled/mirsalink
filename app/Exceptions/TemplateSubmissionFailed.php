<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Meta would not take a template for review (or could not be reached),
 * with the reason already in the member's language.
 */
class TemplateSubmissionFailed extends RuntimeException {}
