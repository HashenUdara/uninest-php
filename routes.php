<?php

/**
 * Routes
 * 
 * Central route definitions for the entire application.
 * Each route maps to a controller function.
 */

// ──────────────────────────────────────
// Public Routes
// ──────────────────────────────────────

route('GET', '/', 'home_index');

// ──────────────────────────────────────
// Auth Routes (guests only)
// ──────────────────────────────────────

route('GET',  '/login',    'auth_login',         ['middleware_guest']);
route('POST', '/login',    'auth_login_post',     ['middleware_guest']);
route('GET',  '/forgot-password', 'auth_forgot_password',      ['middleware_guest']);
route('POST', '/forgot-password', 'auth_forgot_password_post', ['middleware_guest']);
route('GET',  '/reset-password',  'auth_reset_password',       ['middleware_guest']);
route('POST', '/reset-password',  'auth_reset_password_post',  ['middleware_guest']);
route('GET',  '/register', 'auth_register',       ['middleware_guest']);
route('POST', '/register', 'auth_register_post',  ['middleware_guest']);
route('GET',  '/logout',   'auth_logout',         ['middleware_auth']);

// Onboarding (authenticated)
route('GET',  '/onboarding',                     'onboarding_status',             ['middleware_auth']);
route('POST', '/onboarding/moderator/resubmit',  'onboarding_moderator_resubmit', ['middleware_auth']);
route('POST', '/onboarding/student/resubmit',    'onboarding_student_resubmit',   ['middleware_auth']);

// ──────────────────────────────────────
// Dashboard (authenticated)
// ──────────────────────────────────────

route('GET', '/dashboard', 'dashboard_index', ['middleware_auth', 'middleware_onboarding_complete']);

// ──────────────────────────────────────
// Subjects — Student view (authenticated)
// ──────────────────────────────────────

route('GET', '/dashboard/subjects', 'subjects_student_list', ['middleware_auth', 'middleware_onboarding_complete']);

// ──────────────────────────────────────
// Subjects — Moderator CRUD
// ──────────────────────────────────────

route('GET',  '/subjects',              'subjects_index',         ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('GET',  '/subjects/create',       'subjects_create_form',   ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('POST', '/subjects',              'subjects_store',         ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('GET',  '/subjects/{id}/edit',    'subjects_edit_form',     ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('POST', '/subjects/{id}',         'subjects_update_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('POST', '/subjects/{id}/delete',  'subjects_delete_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);

// Students management (admin + moderator scoped actions)
route('GET',  '/students',              'students_index',         ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('GET',  '/students/create',       'students_create_form',   ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/students',              'students_store',         ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('GET',  '/students/{id}/edit',    'students_edit_form',     ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/students/{id}',         'students_update_action', ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/students/{id}/delete',  'students_delete_action', ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/students/{id}/remove',  'students_remove_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('moderator')]);

// Moderator join-request approvals
route('GET',  '/moderator/join-requests',              'moderator_join_requests_index',   ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('moderator')]);
route('POST', '/moderator/join-requests/{id}/approve', 'moderator_join_request_approve',  ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('moderator')]);
route('POST', '/moderator/join-requests/{id}/reject',  'moderator_join_request_reject',   ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('moderator')]);

// Admin approvals
route('GET',  '/admin/batch-requests',              'admin_batch_requests_index',   ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/admin/batch-requests/{id}/approve', 'admin_batch_request_approve',  ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/admin/batch-requests/{id}/reject',  'admin_batch_request_reject',   ['middleware_auth', fn() => middleware_exact_role('admin')]);

route('GET',  '/admin/student-requests',              'admin_student_requests_index',  ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/admin/student-requests/{id}/approve', 'admin_student_request_approve', ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/admin/student-requests/{id}/reject',  'admin_student_request_reject',  ['middleware_auth', fn() => middleware_exact_role('admin')]);
