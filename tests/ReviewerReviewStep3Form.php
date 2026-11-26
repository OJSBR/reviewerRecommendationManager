<?php

/**
 * @file plugins/generic/reviewerRecommendationManager/tests/ReviewerReviewStep3Form.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerReviewStep3Form
 *
 * @brief A form with the short class name of the core reviewer step 3 form, so
 *        the core form calls the same hooks it calls for the real one.
 */

namespace APP\plugins\generic\reviewerRecommendationManager\tests;

use PKP\form\Form;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewerReviewStep3Form extends Form
{
    public function __construct(private ReviewAssignment $reviewAssignment)
    {
        parent::__construct();
    }

    public function getReviewAssignment(): ReviewAssignment
    {
        return $this->reviewAssignment;
    }
}
