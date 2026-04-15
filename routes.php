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
route('GET', '/dashboard/feed', 'feed_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/profile', 'profile_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/profile', 'profile_update_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/profile/password', 'profile_password_update_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/quizzes', 'quizzes_hub_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/gpa', 'gpa_calculator_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/gpa', 'gpa_calculator_store', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/gpa/analytics', 'gpa_analytics_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/gpa/grade-scale', 'gpa_grade_scale_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/gpa/grade-scale', 'gpa_grade_scale_store', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('POST', '/dashboard/gpa/grade-scale/{id}', 'gpa_grade_scale_update', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('POST', '/dashboard/gpa/grade-scale/{id}/delete', 'gpa_grade_scale_delete', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('GET', '/dashboard/community', 'community_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community', 'community_store', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/community/create', 'community_create_form', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/community/reports', 'community_reports_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/reports/{id}/dismiss', 'community_report_dismiss', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/reports/{id}/remove', 'community_report_remove', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/community/{id}', 'community_show', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}', 'community_update_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}/delete', 'community_delete_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}/like', 'community_like_toggle', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}/save', 'community_save_toggle', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}/report', 'community_report_post', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}/question/resolve', 'community_question_resolve', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}/question/reopen', 'community_question_reopen', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}/pin', 'community_pin_post', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}/unpin', 'community_unpin_post', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}/comments', 'community_comment_store', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}/comments/{commentId}/report', 'community_comment_report', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}/comments/{commentId}', 'community_comment_update', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/community/{id}/comments/{commentId}/delete', 'community_comment_delete', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/community/{id}/image', 'community_image', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/kuppi', 'kuppi_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/kuppi/create', 'kuppi_create_form', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/kuppi', 'kuppi_store', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/kuppi/timetable', 'kuppi_timetable_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/kuppi/timetable', 'kuppi_timetable_store', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('POST', '/dashboard/kuppi/timetable/{id}', 'kuppi_timetable_update', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('POST', '/dashboard/kuppi/timetable/{id}/delete', 'kuppi_timetable_delete', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('GET', '/dashboard/kuppi/schedule', 'kuppi_schedule_select_request_step', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('GET', '/dashboard/kuppi/schedule/manual', 'kuppi_schedule_manual_start', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('POST', '/dashboard/kuppi/schedule/select-request', 'kuppi_schedule_select_request_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('GET', '/dashboard/kuppi/schedule/set', 'kuppi_schedule_set_step', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('POST', '/dashboard/kuppi/schedule/set', 'kuppi_schedule_set_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('GET', '/dashboard/kuppi/schedule/assign', 'kuppi_schedule_assign_step', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('POST', '/dashboard/kuppi/schedule/assign', 'kuppi_schedule_assign_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('GET', '/dashboard/kuppi/schedule/review', 'kuppi_schedule_review_step', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('POST', '/dashboard/kuppi/schedule/confirm', 'kuppi_schedule_confirm_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('GET', '/dashboard/kuppi/schedule/success', 'kuppi_schedule_success', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('GET', '/dashboard/kuppi/scheduled', 'kuppi_scheduled_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/kuppi/scheduled/{id}', 'kuppi_scheduled_show', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/kuppi/scheduled/{id}/edit', 'kuppi_scheduled_edit_form', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('POST', '/dashboard/kuppi/scheduled/{id}', 'kuppi_scheduled_update_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('POST', '/dashboard/kuppi/scheduled/{id}/cancel', 'kuppi_scheduled_cancel_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('POST', '/dashboard/kuppi/scheduled/{id}/delete', 'kuppi_scheduled_delete_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('GET', '/dashboard/kuppi/{id}/conductors/apply', 'kuppi_conductor_apply_form', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/kuppi/{id}/conductors/apply', 'kuppi_conductor_apply_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/kuppi/{id}/conductors/{applicationId}/vote', 'kuppi_conductor_vote_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/kuppi/{id}/edit', 'kuppi_edit_form', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/kuppi/{id}', 'kuppi_show', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/kuppi/{id}', 'kuppi_update_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/kuppi/{id}/delete', 'kuppi_delete_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/kuppi/{id}/vote', 'kuppi_vote_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/kuppi/{id}/comments', 'kuppi_comment_store', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/kuppi/{id}/comments/{commentId}', 'kuppi_comment_update', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/kuppi/{id}/comments/{commentId}/delete', 'kuppi_comment_delete', ['middleware_auth', 'middleware_onboarding_complete']);

// ──────────────────────────────────────
// Subjects — Student view (authenticated)
// ──────────────────────────────────────

route('GET', '/dashboard/subjects', 'subjects_student_list', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/subjects/{id}/topics', 'topics_dashboard_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/subjects/{id}/topics/{topicId}/resources', 'resources_topic_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/subjects/{id}/topics/{topicId}/resources/create', 'resources_topic_create_form', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/subjects/{id}/topics/{topicId}/resources/{resourceId}', 'resources_topic_show', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/subjects/{id}/topics/{topicId}/resources', 'resources_topic_store', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/subjects/{id}/quizzes', 'quizzes_subject_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/subjects/{id}/quizzes/create', 'quizzes_subject_create_form', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/subjects/{id}/quizzes', 'quizzes_subject_store', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/subjects/{id}/quizzes/leaderboard', 'quizzes_subject_leaderboard_page', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/subjects/{id}/quizzes/{quizId}', 'quizzes_subject_show', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/subjects/{id}/quizzes/{quizId}/attempts/start', 'quizzes_attempt_start', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/subjects/{id}/quizzes/{quizId}/attempts/{attemptId}', 'quizzes_attempt_take', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/subjects/{id}/quizzes/{quizId}/attempts/{attemptId}/questions/{questionId}/check', 'quizzes_attempt_question_check', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/subjects/{id}/quizzes/{quizId}/attempts/{attemptId}/submit', 'quizzes_attempt_submit', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/subjects/{id}/quizzes/{quizId}/attempts/{attemptId}/result', 'quizzes_attempt_result', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/subjects/{id}/quizzes/{quizId}/comments', 'quizzes_quiz_comment_store', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/subjects/{id}/quizzes/{quizId}/comments/{commentId}', 'quizzes_quiz_comment_update', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/subjects/{id}/quizzes/{quizId}/comments/{commentId}/delete', 'quizzes_quiz_comment_delete', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/subjects/{id}/quizzes/{quizId}/questions/{questionId}/comments', 'quizzes_question_comment_store', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/subjects/{id}/quizzes/{quizId}/questions/{questionId}/comments/{commentId}', 'quizzes_question_comment_update', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/subjects/{id}/quizzes/{quizId}/questions/{questionId}/comments/{commentId}/delete', 'quizzes_question_comment_delete', ['middleware_auth', 'middleware_onboarding_complete']);

// ──────────────────────────────────────
// Subjects — Moderator CRUD
// ──────────────────────────────────────

route('GET',  '/subjects',              'subjects_index',         ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('GET',  '/subjects/create',       'subjects_create_form',   ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('POST', '/subjects',              'subjects_store',         ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('GET',  '/subjects/{id}/edit',    'subjects_edit_form',     ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('POST', '/subjects/{id}',         'subjects_update_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('POST', '/subjects/{id}/delete',  'subjects_delete_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('GET',  '/subjects/{id}/coordinators',                   'subjects_coordinators_page',            ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('POST', '/subjects/{id}/coordinators',                   'subjects_coordinator_assign_action',     ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('POST', '/subjects/{id}/coordinators/{studentId}/delete','subjects_coordinator_unassign_action',   ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);

// Topics — Subject-scoped CRUD (admin + moderator + coordinator)
route('GET',  '/subjects/{id}/topics',                    'topics_index',         ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('GET',  '/subjects/{id}/topics/create',             'topics_create_form',   ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('POST', '/subjects/{id}/topics',                    'topics_store',         ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('GET',  '/subjects/{id}/topics/{topicId}/edit',     'topics_edit_form',     ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('POST', '/subjects/{id}/topics/{topicId}',          'topics_update_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);
route('POST', '/subjects/{id}/topics/{topicId}/delete',   'topics_delete_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('coordinator')]);

// Coordinator subject controls
route('GET',  '/coordinator/subjects',             'subjects_coordinator_index',         ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('coordinator')]);
route('GET',  '/coordinator/subjects/{id}/edit',   'subjects_coordinator_edit_form',     ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('coordinator')]);
route('POST', '/coordinator/subjects/{id}',        'subjects_coordinator_update_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('coordinator')]);
route('GET', '/coordinator/resource-requests', 'resources_coordinator_requests_index', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('coordinator')]);
route('POST', '/coordinator/resource-requests/create/{id}/approve', 'resources_coordinator_create_approve', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('coordinator')]);
route('POST', '/coordinator/resource-requests/create/{id}/reject', 'resources_coordinator_create_reject', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('coordinator')]);
route('POST', '/coordinator/resource-requests/update/{id}/approve', 'resources_coordinator_update_approve', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('coordinator')]);
route('POST', '/coordinator/resource-requests/update/{id}/reject', 'resources_coordinator_update_reject', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('coordinator')]);

// My resources (authenticated)
route('GET', '/my-resources', 'resources_my_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/my-resources/{id}/edit', 'resources_my_edit_form', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/my-resources/{id}', 'resources_my_update_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/my-resources/{id}/delete', 'resources_my_delete_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/my-quizzes', 'quizzes_my_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/my-quiz-analytics', 'quizzes_my_analytics_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/my-quizzes/{id}/edit', 'quizzes_my_edit_form', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/my-quizzes/{id}', 'quizzes_my_update_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/my-quizzes/{id}/submit', 'quizzes_my_submit_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/my-quizzes/{id}/delete', 'quizzes_my_delete_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/quiz-requests', 'quizzes_review_queue_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/dashboard/quiz-analytics', 'quizzes_reviewer_analytics_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/quiz-requests/{id}/approve', 'quizzes_review_approve', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/dashboard/quiz-requests/{id}/reject', 'quizzes_review_reject', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/saved-posts', 'community_saved_posts_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/my-posts', 'community_my_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/my-posts/{id}/edit', 'community_my_edit_form', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/my-posts/{id}', 'community_my_update_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/my-posts/{id}/delete', 'community_my_delete_action', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/my-kuppi-requests', 'kuppi_my_index', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/resources/{id}/rating', 'resources_rating_upsert', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/resources/{id}/comments', 'resources_comment_store', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/resources/{id}/comments/{commentId}', 'resources_comment_update', ['middleware_auth', 'middleware_onboarding_complete']);
route('POST', '/resources/{id}/comments/{commentId}/delete', 'resources_comment_delete', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/resources/{id}/inline', 'resources_inline', ['middleware_auth', 'middleware_onboarding_complete']);
route('GET', '/resources/{id}/download', 'resources_download', ['middleware_auth', 'middleware_onboarding_complete']);

// Students management (admin + moderator scoped actions)
route('GET',  '/students',              'students_index',         ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]);
route('GET',  '/students/create',       'students_create_form',   ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/students',              'students_store',         ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('GET',  '/students/{id}/edit',    'students_edit_form',     ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/students/{id}',         'students_update_action', ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/students/{id}/delete',  'students_delete_action', ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/students/{id}/remove',  'students_remove_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_exact_role('moderator')]);

// Admin provisioning
route('GET',  '/admin/batches',               'batches_index',         ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('GET',  '/admin/batches/create',        'batches_create_form',   ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/admin/batches',               'batches_store',         ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('GET',  '/admin/batches/{id}/edit',     'batches_edit_form',     ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/admin/batches/{id}',          'batches_update_action', ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/admin/batches/{id}/delete',   'batches_delete_action', ['middleware_auth', fn() => middleware_exact_role('admin')]);

route('GET',  '/admin/moderators',               'moderators_index',         ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('GET',  '/admin/moderators/create',        'moderators_create_form',   ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/admin/moderators',               'moderators_store',         ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('GET',  '/admin/moderators/{id}/edit',     'moderators_edit_form',     ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/admin/moderators/{id}',          'moderators_update_action', ['middleware_auth', fn() => middleware_exact_role('admin')]);
route('POST', '/admin/moderators/{id}/delete',   'moderators_delete_action', ['middleware_auth', fn() => middleware_exact_role('admin')]);

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
