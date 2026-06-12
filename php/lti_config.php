<?php
// LTI 1.1 configuration for EduSmart.
// Change CONSUMER_KEY and SHARED_SECRET to match what you register in Moodle.
// This file must NOT be web-accessible on its own — it lives inside php/.

define('LTI_CONSUMER_KEY',  'edusmart_mta_2026');
define('LTI_SHARED_SECRET', 'EduSmart_MTA_LTI_2026!x7Qp92');

// Demo mode bypasses OAuth and creates a fake session for local browser testing.
// KEEP THIS FALSE in any environment that Moodle points at.
define('LTI_DEMO_MODE_ENABLED', false);
