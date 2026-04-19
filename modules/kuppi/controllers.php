<?php

/**
 * Kuppi Module — Controllers Loader
 *
 * Split into focused controller parts to improve maintainability:
 * - support/policies/utilities
 * - request/timetable actions
 * - request detail + conductor + comments
 * - scheduling helpers
 * - scheduling wizard
 * - scheduled sessions
 */

require_once __DIR__ . '/controllers/support.php';
require_once __DIR__ . '/controllers/requests_overview.php';
require_once __DIR__ . '/controllers/requests_actions.php';
require_once __DIR__ . '/controllers/schedule_support.php';
require_once __DIR__ . '/controllers/schedule_wizard.php';
require_once __DIR__ . '/controllers/scheduled_sessions.php';
