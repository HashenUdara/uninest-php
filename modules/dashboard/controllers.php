<?php

/**
 * Dashboard Module — Controllers
 * 
 * Routes to the correct dashboard view based on user role.
 */

function dashboard_index(): void
{
    $user = auth_user();
    $role = $user['role'] ?? 'student';
    $data = ['user' => $user];

    // Load role-specific dashboard data
    switch ($role) {
        case 'admin':
            try {
                $data['user_count']    = db_count('users');
                $data['subject_count'] = db_count('subjects');
                $onboardingCounts = onboarding_admin_counts();
                $data['pending_batch_requests'] = $onboardingCounts['pending_batch_requests'];
                $data['pending_student_requests'] = $onboardingCounts['pending_student_requests'];
                $data['pending_quiz_requests'] = quizzes_pending_count_for_reviewer((int) $user['id'], 'admin', 0);
            } catch (\PDOException) {
                $data['user_count']    = 0;
                $data['subject_count'] = 0;
                $data['pending_batch_requests'] = 0;
                $data['pending_student_requests'] = 0;
                $data['pending_quiz_requests'] = 0;
            }
            $viewName = 'admin';
            break;

        case 'moderator':
            try {
                $batchId = (int) ($user['batch_id'] ?? 0);
                $data['subjects']      = db_fetch_all('SELECT * FROM subjects WHERE batch_id = ? ORDER BY created_at DESC LIMIT 10', [$batchId]);
                $data['subject_count'] = (int) db_fetch('SELECT COUNT(*) AS cnt FROM subjects WHERE batch_id = ?', [$batchId])['cnt'];
                $data['pending_student_requests'] = onboarding_moderator_pending_student_request_count((int) $user['id']);
                $data['pending_quiz_requests'] = quizzes_pending_count_for_reviewer((int) $user['id'], 'moderator', $batchId);
                $data['batch'] = onboarding_find_moderator_batch((int) $user['id']);

                if (!empty($data['batch']['batch_code'])) {
                    $data['invite_link'] = base_url('register') . '?role=student&batch_code=' . urlencode($data['batch']['batch_code']);
                    $data['invite_qr_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($data['invite_link']);
                } else {
                    $data['invite_link'] = null;
                    $data['invite_qr_url'] = null;
                }
            } catch (\PDOException) {
                $data['subjects']      = [];
                $data['subject_count'] = 0;
                $data['pending_student_requests'] = 0;
                $data['pending_quiz_requests'] = 0;
                $data['batch'] = null;
                $data['invite_link'] = null;
                $data['invite_qr_url'] = null;
            }
            $viewName = 'moderator';
            break;

        case 'coordinator':
            try {
                $data['subjects'] = subjects_all_for_coordinator((int) $user['id']);
                $data['pending_resource_requests'] = resources_coordinator_pending_count((int) $user['id']);
                $data['pending_quiz_requests'] = quizzes_pending_count_for_reviewer((int) $user['id'], 'coordinator', (int) ($user['batch_id'] ?? 0));
            } catch (\PDOException) {
                $data['subjects'] = [];
                $data['pending_resource_requests'] = 0;
                $data['pending_quiz_requests'] = 0;
            }
            $viewName = 'coordinator';
            break;

        default: // student
            try {
                $batchId = (int) ($user['batch_id'] ?? 0);
                $data['subjects'] = db_fetch_all('SELECT * FROM subjects WHERE batch_id = ? ORDER BY name ASC', [$batchId]);
            } catch (\PDOException) {
                $data['subjects'] = [];
            }
            $viewName = 'student';
            break;
    }

    view('dashboard::' . $viewName, $data, 'dashboard');
}
