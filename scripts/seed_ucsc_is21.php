#!/usr/bin/env php
<?php

declare(strict_types=1);

use Dotenv\Dotenv;

const SEED_BATCH_CODE = 'BATCH-UCSC-IS21';
const SEED_EMAIL_DOMAIN = 'is21.ucsc.uninest.local';
const SEED_STORAGE_PREFIX = 'storage/resources/seed/is21';
const SEED_PASSWORD_HASH_123 = '$2y$12$4sVZY2Cz8Lu71OKcmQa2lec45cNXKraA4OQryLa8.hNVQWDTBiOHu';

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);

require $basePath . '/vendor/autoload.php';

Dotenv::createImmutable($basePath)->safeLoad();

try {
    $pdo = seed_connect($basePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    seed_log('Starting UCSC IS21 complete dataset seeding...');

    $seedStats = seed_ucsc_is21($pdo, $basePath);

    seed_log('Seeding completed successfully.');
    seed_log('Summary:');
    foreach ($seedStats as $label => $value) {
        seed_log(sprintf('  - %s: %s', $label, (string) $value));
    }
} catch (Throwable $e) {
    fwrite(STDERR, "\n[seed:ucsc-is21] FAILED: " . $e->getMessage() . "\n");
    exit(1);
}

exit(0);

function seed_ucsc_is21(PDO $pdo, string $basePath): array
{
    $seedStartedAt = microtime(true);

    seed_log('Step 1/18: cleanup previous IS21 dataset...');
    seed_clean_previous_dataset($pdo, $basePath);
    seed_log('Step 1/18 done.');

    seed_log('Step 2/18: ensuring admin + UCSC university...');
    $adminUserId = seed_ensure_admin_user($pdo);
    $universityId = seed_ensure_ucsc_university($pdo);
    seed_log('Step 2/18 done.');

    seed_log('Step 3/18: scanning seed resource assets...');
    $assetPool = seed_collect_resource_assets($basePath);
    $assetCopyCounter = 1;
    $copiedAssetCache = [];
    $assetPoolCount = 0;
    foreach ($assetPool as $assetItems) {
        if (is_array($assetItems)) {
            $assetPoolCount += count($assetItems);
        }
    }
    seed_log('Step 3/18 done. Found ' . $assetPoolCount . ' asset files.');

    $stats = [
        'users' => 0,
        'batches' => 0,
        'subjects' => 0,
        'coordinator_assignments' => 0,
        'topics' => 0,
        'resources' => 0,
        'resource_ratings' => 0,
        'resource_saves' => 0,
        'announcements' => 0,
        'feed_posts' => 0,
        'quizzes' => 0,
        'quiz_attempts' => 0,
        'kuppi_requests' => 0,
        'kuppi_sessions' => 0,
        'gpa_term_records' => 0,
        'comments' => 0,
    ];

    $seedUsers = [
        'moderators' => [],
        'coordinators' => [],
        'students' => [],
    ];

    seed_log('Step 4/18: opening database transaction for bulk inserts...');
    $txStart = microtime(true);
    $pdo->beginTransaction();

    try {
        $moderatorNames = [
            'Dulanga Madushanka',
            'Nethmi Ranasinghe',
            'Rivindu Weerakoon',
        ];

        $coordinatorNames = [
            'Ayesh Fernando',
            'Chathurangi Perera',
            'Lakshan Jayawardena',
            'Nipuni Karunaratne',
            'Sachithra Kodithuwakku',
            'Thilina Dissanayake',
            'Yashoda Wickramasinghe',
            'Dinusha Gunasekara',
            'Malshi Senanayake',
            'Pasindu Herath',
            'Sahan Maduranga',
            'Vihangi Peiris',
        ];

        $studentNames = seed_generate_sri_lankan_student_names(100);

        seed_log('Step 5/18: creating moderators...');
        foreach ($moderatorNames as $index => $name) {
            $email = sprintf('moderator%02d@%s', $index + 1, SEED_EMAIL_DOMAIN);
            $userId = seed_insert_user($pdo, [
                'name' => $name,
                'email' => $email,
                'role' => 'moderator',
                'academic_year' => 2,
                'university_id' => $universityId,
                'batch_id' => null,
                'first_approved_batch_id' => null,
                'created_at' => seed_random_datetime('-420 days', '-320 days'),
                'updated_at' => seed_random_datetime('-60 days', '-1 days'),
            ]);

            $seedUsers['moderators'][] = [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
            ];
            $stats['users']++;
            seed_log_progress('Moderators created', $index + 1, count($moderatorNames), 1);
        }

        $batchCreatedAt = seed_random_datetime('-380 days', '-360 days');
        $batchUpdatedAt = seed_random_datetime('-90 days', '-3 days');

        $batchId = seed_insert_batch($pdo, [
            'batch_code' => SEED_BATCH_CODE,
            'name' => 'UCSC IS 21 Batch',
            'program' => 'BSc Honours in Information Systems',
            'intake_year' => 2021,
            'university_id' => $universityId,
            'moderator_user_id' => (int) $seedUsers['moderators'][0]['id'],
            'status' => 'approved',
            'rejection_reason' => null,
            'reviewed_by' => $adminUserId,
            'reviewed_at' => seed_random_datetime('-340 days', '-330 days'),
            'created_at' => $batchCreatedAt,
            'updated_at' => $batchUpdatedAt,
        ]);
        $stats['batches']++;
        seed_log('Batch created: ' . SEED_BATCH_CODE . ' (id=' . $batchId . ').');

        foreach ($seedUsers['moderators'] as $moderator) {
            seed_update_user_batch($pdo, (int) $moderator['id'], $batchId, null);
        }

        seed_log('Step 6/18: creating coordinators...');
        foreach ($coordinatorNames as $index => $name) {
            $email = sprintf('coordinator%02d@%s', $index + 1, SEED_EMAIL_DOMAIN);
            $coordinatorId = seed_insert_user($pdo, [
                'name' => $name,
                'email' => $email,
                'role' => 'coordinator',
                'academic_year' => ($index % 2) + 1,
                'university_id' => $universityId,
                'batch_id' => $batchId,
                'first_approved_batch_id' => null,
                'created_at' => seed_random_datetime('-360 days', '-180 days'),
                'updated_at' => seed_random_datetime('-30 days', '-1 days'),
            ]);

            $seedUsers['coordinators'][] = [
                'id' => $coordinatorId,
                'name' => $name,
                'email' => $email,
            ];
            $stats['users']++;
            seed_log_progress('Coordinators created', $index + 1, count($coordinatorNames), 3);
        }

        seed_log('Step 7/18: creating 100 students + approved join requests...');
        foreach ($studentNames as $index => $name) {
            $email = sprintf('student%03d@%s', $index + 1, SEED_EMAIL_DOMAIN);
            $academicYear = $index < 50 ? 1 : 2;
            $createdAt = seed_random_datetime('-340 days', '-20 days');
            $updatedAt = seed_random_datetime('-20 days', '-1 days');

            $studentId = seed_insert_user($pdo, [
                'name' => $name,
                'email' => $email,
                'role' => 'student',
                'academic_year' => $academicYear,
                'university_id' => $universityId,
                'batch_id' => $batchId,
                'first_approved_batch_id' => $batchId,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            $seedUsers['students'][] = [
                'id' => $studentId,
                'name' => $name,
                'email' => $email,
                'academic_year' => $academicYear,
            ];
            $stats['users']++;

            seed_insert_student_batch_request($pdo, [
                'student_user_id' => $studentId,
                'requested_batch_id' => $batchId,
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by' => (int) $seedUsers['moderators'][0]['id'],
                'reviewed_role' => 'moderator',
                'reviewed_at' => seed_random_datetime('-320 days', '-15 days'),
                'created_at' => seed_random_datetime('-340 days', '-20 days'),
                'updated_at' => seed_random_datetime('-20 days', '-1 days'),
            ]);

            seed_log_progress('Students created', $index + 1, count($studentNames), 10);
        }

        $subjects = seed_is21_subject_catalog();
        $subjectRows = [];
        seed_log('Step 8/18: creating subjects + coordinator assignments...');
        foreach ($subjects as $subjectIndex => $subject) {
            $subjectId = seed_insert_subject($pdo, [
                'batch_id' => $batchId,
                'code' => $subject['code'],
                'name' => $subject['name'],
                'description' => $subject['description'],
                'credits' => $subject['credits'],
                'academic_year' => $subject['academic_year'],
                'semester' => $subject['semester'],
                'created_by' => (int) $seedUsers['moderators'][0]['id'],
                'created_at' => seed_random_datetime('-320 days', '-240 days'),
                'updated_at' => seed_random_datetime('-30 days', '-1 days'),
            ]);

            $coordinator = $seedUsers['coordinators'][$subjectIndex % count($seedUsers['coordinators'])];
            seed_insert_subject_coordinator($pdo, [
                'subject_id' => $subjectId,
                'student_user_id' => (int) $coordinator['id'],
                'assigned_by' => (int) $seedUsers['moderators'][0]['id'],
                'created_at' => seed_random_datetime('-250 days', '-120 days'),
            ]);
            $stats['coordinator_assignments']++;

            $subjectRows[] = [
                'id' => $subjectId,
                'code' => $subject['code'],
                'name' => $subject['name'],
                'academic_year' => $subject['academic_year'],
                'semester' => $subject['semester'],
                'credits' => $subject['credits'],
                'is_non_gpa' => $subject['is_non_gpa'],
                'coordinator_user_id' => (int) $coordinator['id'],
            ];
            $stats['subjects']++;
            seed_log_progress('Subjects created', $subjectIndex + 1, count($subjects), 5);
        }

        $topicRows = [];
        $topicsBySubject = [];
        seed_log('Step 9/18: creating topics for all subjects...');
        $topicTotal = 0;
        foreach ($subjectRows as $subjectForCount) {
            $topicTotal += count(seed_topic_templates_for_subject((string) $subjectForCount['code'], (string) $subjectForCount['name']));
        }
        $topicCreated = 0;
        foreach ($subjectRows as $subject) {
            $topicTemplates = seed_topic_templates_for_subject((string) $subject['code'], (string) $subject['name']);

            foreach ($topicTemplates as $order => $template) {
                $topicId = seed_insert_topic($pdo, [
                    'subject_id' => (int) $subject['id'],
                    'title' => $template['title'],
                    'description' => $template['description'],
                    'sort_order' => $order + 1,
                    'created_by' => (int) $subject['coordinator_user_id'],
                    'created_at' => seed_random_datetime('-280 days', '-50 days'),
                    'updated_at' => seed_random_datetime('-30 days', '-1 days'),
                ]);

                $topicRows[] = [
                    'id' => $topicId,
                    'subject_id' => (int) $subject['id'],
                    'subject_code' => $subject['code'],
                    'topic_key' => $template['key'],
                    'topic_title' => $template['title'],
                ];
                $topicsBySubject[(int) $subject['id']][] = [
                    'id' => $topicId,
                    'key' => $template['key'],
                    'title' => $template['title'],
                ];
                $stats['topics']++;
                $topicCreated++;
                seed_log_progress('Topics created', $topicCreated, $topicTotal, 25);
            }
        }

        $publishedResourceRows = [];
        seed_log('Step 10/18: creating resources from all asset files + curated links...');

        $filePlans = [];
        foreach ($subjectRows as $subject) {
            $subjectAssets = (array) ($assetPool[(string) $subject['code']] ?? []);
            foreach ($subjectAssets as $assetMeta) {
                if (is_string($assetMeta)) {
                    $assetMeta = [
                        'source_path' => $assetMeta,
                        'source_group' => 'subject_material',
                    ];
                }
                if (!is_array($assetMeta)) {
                    continue;
                }
                $filePlans[] = [
                    'subject' => $subject,
                    'asset' => $assetMeta,
                ];
            }
        }

        $genericAssets = (array) ($assetPool['_generic'] ?? []);
        foreach ($genericAssets as $genericIndex => $assetMeta) {
            if (is_string($assetMeta)) {
                $assetMeta = [
                    'source_path' => $assetMeta,
                    'source_group' => 'generic',
                ];
            }
            if (!is_array($assetMeta)) {
                continue;
            }

            $targetSubject = seed_match_subject_for_asset($subjectRows, $assetMeta);
            if ($targetSubject === null) {
                continue;
            }
            $filePlans[] = [
                'subject' => $targetSubject,
                'asset' => $assetMeta,
            ];

            if (($genericIndex + 1) % 40 === 0 || $genericIndex + 1 === count($genericAssets)) {
                seed_log_progress('Generic assets mapped by subject', $genericIndex + 1, count($genericAssets), 40);
            }
        }

        seed_log('File resource plans prepared: ' . count($filePlans));

        $uploaderPool = array_merge($seedUsers['students'], $seedUsers['coordinators']);
        foreach ($filePlans as $planIndex => $plan) {
            $subject = (array) ($plan['subject'] ?? []);
            $assetMeta = (array) ($plan['asset'] ?? []);

            $topicsForSubject = (array) ($topicsBySubject[(int) ($subject['id'] ?? 0)] ?? []);
            $topic = seed_pick_topic_for_asset($topicsForSubject, $assetMeta);
            if ($topic === null) {
                continue;
            }

            $uploader = $uploaderPool[$planIndex % count($uploaderPool)];
            $reviewer = $seedUsers['coordinators'][$planIndex % count($seedUsers['coordinators'])];
            $fileStatus = ($planIndex % 29 === 0)
                ? 'rejected'
                : (($planIndex % 11 === 0) ? 'pending' : 'published');

            $copiedMeta = seed_copy_resource_asset_cached(
                $basePath,
                $assetMeta,
                (string) ($subject['code'] ?? 'ISXX'),
                $assetCopyCounter,
                $copiedAssetCache
            );

            $resourceId = seed_insert_resource($pdo, [
                'topic_id' => (int) $topic['id'],
                'uploaded_by_user_id' => (int) $uploader['id'],
                'title' => seed_resource_file_title((string) ($subject['code'] ?? 'ISXX'), (string) $copiedMeta['file_name']),
                'description' => 'Seeded file resource for ' . (string) ($subject['name'] ?? 'subject') . ' • Topic: ' . (string) ($topic['title'] ?? 'Study Materials') . '.',
                'category' => seed_resource_category_for_extension((string) $copiedMeta['file_name']),
                'category_other' => null,
                'source_type' => 'file',
                'file_path' => $copiedMeta['file_path'],
                'file_name' => $copiedMeta['file_name'],
                'file_mime' => $copiedMeta['file_mime'],
                'file_size' => $copiedMeta['file_size'],
                'external_url' => null,
                'status' => $fileStatus,
                'rejection_reason' => $fileStatus === 'rejected' ? 'Please verify file quality before publishing.' : null,
                'reviewed_by_user_id' => $fileStatus === 'pending' ? null : (int) $reviewer['id'],
                'reviewed_at' => $fileStatus === 'pending' ? null : seed_random_datetime('-45 days', '-1 days'),
                'created_at' => seed_random_datetime('-180 days', '-2 days'),
                'updated_at' => seed_random_datetime('-40 days', '-1 days'),
            ]);
            $stats['resources']++;

            $resourceRow = [
                'id' => $resourceId,
                'topic_id' => (int) $topic['id'],
                'subject_id' => (int) ($subject['id'] ?? 0),
                'uploaded_by_user_id' => (int) $uploader['id'],
                'status' => $fileStatus,
            ];
            if ($fileStatus === 'published') {
                $publishedResourceRows[] = $resourceRow;
            }

            seed_log_progress('File resources created', $planIndex + 1, count($filePlans), 25);
        }

        seed_log('Step 10/18 continued: adding topic-level curated links...');
        foreach ($topicRows as $topicIndex => $topic) {
            $subject = seed_find_subject_by_id($subjectRows, (int) $topic['subject_id']);
            if ($subject === null) {
                continue;
            }

            $uploader = $uploaderPool[$topicIndex % count($uploaderPool)];
            $reviewer = $seedUsers['coordinators'][$topicIndex % count($seedUsers['coordinators'])];
            $linkStatus = ($topicIndex % 19 === 0)
                ? 'rejected'
                : (($topicIndex % 13 === 0) ? 'pending' : 'published');

            $linkCategory = seed_resource_link_category_for_topic_key((string) ($topic['topic_key'] ?? 'reference'));

            $linkResourceId = seed_insert_resource($pdo, [
                'topic_id' => (int) $topic['id'],
                'uploaded_by_user_id' => (int) $uploader['id'],
                'title' => (string) $subject['code'] . ' • ' . (string) $topic['topic_title'] . ' • Reference Link',
                'description' => 'External reading and tutorial reference for ' . (string) $subject['name'] . '.',
                'category' => $linkCategory,
                'category_other' => null,
                'source_type' => 'link',
                'file_path' => null,
                'file_name' => null,
                'file_mime' => null,
                'file_size' => null,
                'external_url' => seed_demo_resource_link((string) $subject['code'], (int) $topic['id']),
                'status' => $linkStatus,
                'rejection_reason' => $linkStatus === 'rejected' ? 'Link quality does not meet publication standards yet.' : null,
                'reviewed_by_user_id' => $linkStatus === 'pending' ? null : (int) $reviewer['id'],
                'reviewed_at' => $linkStatus === 'pending' ? null : seed_random_datetime('-35 days', '-1 days'),
                'created_at' => seed_random_datetime('-160 days', '-1 days'),
                'updated_at' => seed_random_datetime('-30 days', '-1 days'),
            ]);
            $stats['resources']++;

            if ($linkStatus === 'published') {
                $publishedResourceRows[] = [
                    'id' => $linkResourceId,
                    'topic_id' => (int) $topic['id'],
                    'subject_id' => (int) $subject['id'],
                    'uploaded_by_user_id' => (int) $uploader['id'],
                    'status' => $linkStatus,
                ];
            }

            seed_log_progress('Curated link resources created', $topicIndex + 1, count($topicRows), 30);
        }

        $pendingUpdateCandidates = array_values(array_filter($publishedResourceRows, static fn(array $row): bool => (int) $row['id'] % 3 === 0));
        $pendingUpdateCandidates = array_slice($pendingUpdateCandidates, 0, 20);
        seed_log('Step 10/18 continued: creating pending/rejected resource update requests...');
        foreach ($pendingUpdateCandidates as $index => $resource) {
            $requester = $seedUsers['students'][$index % count($seedUsers['students'])];
            $status = ($index % 4 === 0) ? 'rejected' : 'pending';

            seed_insert_resource_update_request($pdo, [
                'resource_id' => (int) $resource['id'],
                'requested_by_user_id' => (int) $requester['id'],
                'title' => 'Updated ' . $index . ' - Extended Notes',
                'description' => 'Proposed revision with clearer examples and cleanup updates.',
                'category' => 'Short Notes',
                'category_other' => null,
                'source_type' => 'link',
                'file_path' => null,
                'file_name' => null,
                'file_mime' => null,
                'file_size' => null,
                'external_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'status' => $status,
                'rejection_reason' => $status === 'rejected' ? 'Please align the update with official lecture scope.' : null,
                'reviewed_by_user_id' => $status === 'rejected' ? (int) $seedUsers['coordinators'][$index % count($seedUsers['coordinators'])]['id'] : null,
                'reviewed_at' => $status === 'rejected' ? seed_random_datetime('-25 days', '-1 days') : null,
                'created_at' => seed_random_datetime('-45 days', '-1 days'),
                'updated_at' => seed_random_datetime('-20 days', '-1 days'),
            ]);
            seed_log_progress('Resource update requests created', $index + 1, count($pendingUpdateCandidates), 5);
        }

        $eligibleRaters = $seedUsers['students'];
        seed_log('Step 10/18 continued: adding ratings + saves for published resources...');
        foreach ($publishedResourceRows as $index => $resource) {
            $ratingsCount = $index % 6;
            if ($ratingsCount <= 0) {
                continue;
            }

            $raterOffset = $index % count($eligibleRaters);
            for ($i = 0; $i < $ratingsCount; $i++) {
                $rater = $eligibleRaters[($raterOffset + $i) % count($eligibleRaters)];
                if ((int) $rater['id'] === (int) $resource['uploaded_by_user_id']) {
                    continue;
                }

                seed_insert_resource_rating($pdo, [
                    'resource_id' => (int) $resource['id'],
                    'student_user_id' => (int) $rater['id'],
                    'rating' => (($i + $index) % 5) + 1,
                    'created_at' => seed_random_datetime('-35 days', '-1 days'),
                    'updated_at' => seed_random_datetime('-10 days', '-1 days'),
                ]);
                $stats['resource_ratings']++;
            }

            $saveCount = $index % 4;
            $saverPool = array_merge($seedUsers['students'], $seedUsers['coordinators'], $seedUsers['moderators']);
            $saveOffset = $index % count($saverPool);
            for ($j = 0; $j < $saveCount; $j++) {
                $saver = $saverPool[($saveOffset + $j) % count($saverPool)];

                seed_insert_resource_save($pdo, [
                    'resource_id' => (int) $resource['id'],
                    'user_id' => (int) $saver['id'],
                    'created_at' => seed_random_datetime('-20 days', '-1 days'),
                ]);
                $stats['resource_saves']++;
            }

            seed_log_progress('Published resources enriched', $index + 1, count($publishedResourceRows), 25);
        }

        $announcementRows = [];
        $announcementTemplates = [
            'Semester Kickoff Briefing',
            'IS1210 Lab Assessment Window',
            'Mid-Semester Academic Advisory',
            'Exam Registration Reminder',
            'University Holiday Notice',
            'Guest Lecture: Product Thinking',
            'Library Extended Hours',
            'Coursework Submission Timeline',
            'Batch Mentoring Sessions',
            'Career Preparation Workshop',
            'Project Demo Guidelines',
            'Final Review Week Plan',
        ];

        seed_log('Step 11/18: creating announcements...');
        foreach ($announcementTemplates as $index => $title) {
            $subjectRef = $subjectRows[$index % count($subjectRows)];
            $author = ($index % 2 === 0)
                ? $seedUsers['moderators'][$index % count($seedUsers['moderators'])]
                : $seedUsers['coordinators'][$index % count($seedUsers['coordinators'])];

            $announcementId = seed_insert_announcement($pdo, [
                'batch_id' => $batchId,
                'subject_id' => ($index % 3 === 0) ? (int) $subjectRef['id'] : null,
                'author_user_id' => (int) $author['id'],
                'title' => $title,
                'body' => seed_announcement_body($title, $subjectRef['name']),
                'is_pinned' => $index === 0 ? 1 : 0,
                'pinned_by_user_id' => $index === 0 ? (int) $seedUsers['moderators'][0]['id'] : null,
                'pinned_at' => $index === 0 ? seed_random_datetime('-10 days', '-1 days') : null,
                'created_at' => seed_random_datetime('-70 days', '-1 days'),
                'updated_at' => seed_random_datetime('-10 days', '-1 days'),
            ]);
            $announcementRows[] = $announcementId;
            $stats['announcements']++;
            seed_log_progress('Announcements created', $index + 1, count($announcementTemplates), 3);
        }

        $feedPostRows = [];
        $postTypes = ['general', 'discussion', 'question', 'resource_share'];
        $feedAuthorPool = array_merge($seedUsers['students'], $seedUsers['coordinators'], $seedUsers['moderators']);

        seed_log('Step 12/18: creating community feed posts + interactions...');
        for ($i = 0; $i < 140; $i++) {
            $subject = ($i % 5 === 0) ? null : $subjectRows[$i % count($subjectRows)];
            $postType = $postTypes[$i % count($postTypes)];
            $author = $feedAuthorPool[$i % count($feedAuthorPool)];
            $isQuestion = $postType === 'question';
            $isPinned = ($i === 3) ? 1 : 0;

            $postId = seed_insert_feed_post($pdo, [
                'batch_id' => $batchId,
                'subject_id' => $subject['id'] ?? null,
                'author_user_id' => (int) $author['id'],
                'post_type' => $postType,
                'body' => seed_feed_post_body($postType, $subject['name'] ?? null),
                'image_path' => null,
                'image_name' => null,
                'image_mime' => null,
                'image_size' => null,
                'is_pinned' => $isPinned,
                'pinned_by_user_id' => $isPinned ? (int) $seedUsers['moderators'][0]['id'] : null,
                'pinned_at' => $isPinned ? seed_random_datetime('-14 days', '-1 days') : null,
                'is_resolved' => $isQuestion && ($i % 3 === 0) ? 1 : 0,
                'resolved_by_user_id' => $isQuestion && ($i % 3 === 0) ? (int) $author['id'] : null,
                'resolved_at' => $isQuestion && ($i % 3 === 0) ? seed_random_datetime('-9 days', '-1 days') : null,
                'edited_at' => ($i % 7 === 0) ? seed_random_datetime('-8 days', '-1 days') : null,
                'created_at' => seed_random_datetime('-120 days', '-1 days'),
                'updated_at' => seed_random_datetime('-20 days', '-1 days'),
            ]);

            $feedPostRows[] = [
                'id' => $postId,
                'author_user_id' => (int) $author['id'],
            ];
            $stats['feed_posts']++;

            $likeCount = $i % 8;
            for ($j = 0; $j < $likeCount; $j++) {
                $liker = $feedAuthorPool[($i + $j + 5) % count($feedAuthorPool)];
                if ((int) $liker['id'] === (int) $author['id']) {
                    continue;
                }

                seed_insert_feed_like($pdo, [
                    'post_id' => $postId,
                    'user_id' => (int) $liker['id'],
                    'created_at' => seed_random_datetime('-30 days', '-1 days'),
                ]);
            }

            $saveCount = $i % 5;
            for ($k = 0; $k < $saveCount; $k++) {
                $saver = $feedAuthorPool[($i + $k + 7) % count($feedAuthorPool)];
                seed_insert_feed_save($pdo, [
                    'post_id' => $postId,
                    'user_id' => (int) $saver['id'],
                    'created_at' => seed_random_datetime('-25 days', '-1 days'),
                ]);
            }

            seed_log_progress('Feed posts created', $i + 1, 140, 20);
        }

        $quizRows = [];
        seed_log('Step 13/18: creating approved subject quizzes...');
        foreach ($subjectRows as $index => $subject) {
            $creator = ($index % 2 === 0)
                ? $seedUsers['coordinators'][$index % count($seedUsers['coordinators'])]
                : $seedUsers['students'][$index % count($seedUsers['students'])];

            $quizId = seed_insert_quiz($pdo, [
                'subject_id' => (int) $subject['id'],
                'created_by_user_id' => (int) $creator['id'],
                'title' => $subject['code'] . ' ' . $subject['name'] . ' Mastery Quiz',
                'description' => 'Practice and exam-style MCQs covering core concepts, applied scenarios, and revision checkpoints.',
                'duration_minutes' => 25 + (($index % 5) * 5),
                'mode' => ($index % 2 === 0) ? 'practice' : 'exam',
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by_user_id' => (int) $seedUsers['moderators'][0]['id'],
                'reviewed_at' => seed_random_datetime('-30 days', '-1 days'),
                'created_at' => seed_random_datetime('-130 days', '-3 days'),
                'updated_at' => seed_random_datetime('-20 days', '-1 days'),
            ]);

            $questionRows = seed_insert_quiz_questions_with_options($pdo, $quizId, $subject['name'], 6);
            $quizRows[] = [
                'id' => $quizId,
                'subject_id' => (int) $subject['id'],
                'mode' => ($index % 2 === 0) ? 'practice' : 'exam',
                'duration_minutes' => 25 + (($index % 5) * 5),
                'questions' => $questionRows,
                'status' => 'approved',
            ];
            $stats['quizzes']++;
            seed_log_progress('Approved quizzes created', $index + 1, count($subjectRows), 5);
        }

        seed_log('Step 13/18 continued: creating draft/pending/rejected student quizzes...');
        for ($extra = 0; $extra < 12; $extra++) {
            $subject = $subjectRows[$extra % count($subjectRows)];
            $creator = $seedUsers['students'][($extra * 3) % count($seedUsers['students'])];
            $status = match ($extra % 3) {
                0 => 'pending',
                1 => 'rejected',
                default => 'draft',
            };

            $quizId = seed_insert_quiz($pdo, [
                'subject_id' => (int) $subject['id'],
                'created_by_user_id' => (int) $creator['id'],
                'title' => $subject['code'] . ' Student Draft Quiz ' . ($extra + 1),
                'description' => 'Student-authored draft for coordinator review and publication.',
                'duration_minutes' => 20 + (($extra % 4) * 5),
                'mode' => ($extra % 2 === 0) ? 'exam' : 'practice',
                'status' => $status,
                'rejection_reason' => $status === 'rejected' ? 'Please improve answer-option clarity before resubmission.' : null,
                'reviewed_by_user_id' => in_array($status, ['pending', 'draft'], true) ? null : (int) $seedUsers['coordinators'][$extra % count($seedUsers['coordinators'])]['id'],
                'reviewed_at' => in_array($status, ['pending', 'draft'], true) ? null : seed_random_datetime('-10 days', '-1 days'),
                'created_at' => seed_random_datetime('-50 days', '-1 days'),
                'updated_at' => seed_random_datetime('-10 days', '-1 days'),
            ]);

            seed_insert_quiz_questions_with_options($pdo, $quizId, $subject['name'], 4);
            $stats['quizzes']++;
            seed_log_progress('Student-state quizzes created', $extra + 1, 12, 4);
        }

        $attemptCount = 0;
        $approvedQuizzes = array_values(array_filter($quizRows, static fn(array $quiz): bool => $quiz['status'] === 'approved'));
        seed_log('Step 14/18: creating quiz attempts + answers...');
        foreach ($approvedQuizzes as $quizIndex => $quiz) {
            $attemptsForQuiz = $quizIndex < 20 ? 8 : 4;
            for ($a = 0; $a < $attemptsForQuiz; $a++) {
                $student = $seedUsers['students'][($quizIndex * 7 + $a) % count($seedUsers['students'])];
                $startedAt = seed_random_datetime('-40 days', '-1 days');
                $expiresAt = seed_datetime_add_minutes($startedAt, (int) $quiz['duration_minutes']);

                $status = ($a % 9 === 0)
                    ? 'auto_submitted'
                    : (($a % 11 === 0) ? 'in_progress' : 'submitted');

                $selectedAnswers = [];
                $correctCount = 0;

                foreach ($quiz['questions'] as $question) {
                    $options = $question['options'];
                    $selectedOption = $options[($a + (int) $question['sort_order']) % count($options)];
                    $isCorrect = (int) $selectedOption['is_correct'] === 1;

                    if ($status === 'in_progress' && (int) $question['sort_order'] > 3) {
                        continue;
                    }

                    $selectedAnswers[] = [
                        'question_id' => (int) $question['id'],
                        'selected_option_id' => (int) $selectedOption['id'],
                        'is_correct' => $isCorrect ? 1 : 0,
                    ];

                    if ($isCorrect) {
                        $correctCount++;
                    }
                }

                $totalQuestions = count($quiz['questions']);
                $attemptedQuestions = count($selectedAnswers);
                $effectiveTotal = $status === 'in_progress' ? $attemptedQuestions : $totalQuestions;
                $scorePercent = $effectiveTotal > 0 ? round(($correctCount / $effectiveTotal) * 100, 2) : 0.00;

                $submittedAt = null;
                if ($status === 'submitted') {
                    $submittedAt = seed_datetime_add_minutes($startedAt, mt_rand(8, (int) $quiz['duration_minutes']));
                }
                if ($status === 'auto_submitted') {
                    $submittedAt = seed_datetime_add_minutes($expiresAt, mt_rand(1, 9));
                }

                $attemptId = seed_insert_quiz_attempt($pdo, [
                    'quiz_id' => (int) $quiz['id'],
                    'user_id' => (int) $student['id'],
                    'status' => $status,
                    'started_at' => $startedAt,
                    'expires_at' => $expiresAt,
                    'submitted_at' => $submittedAt,
                    'correct_count' => $correctCount,
                    'total_questions' => $effectiveTotal,
                    'score_percent' => $scorePercent,
                    'created_at' => $startedAt,
                    'updated_at' => $submittedAt ?? seed_random_datetime('-5 days', '-1 days'),
                ]);

                foreach ($selectedAnswers as $answer) {
                    seed_insert_quiz_attempt_answer($pdo, [
                        'attempt_id' => $attemptId,
                        'question_id' => (int) $answer['question_id'],
                        'selected_option_id' => (int) $answer['selected_option_id'],
                        'is_correct' => (int) $answer['is_correct'],
                        'created_at' => $startedAt,
                        'updated_at' => $submittedAt ?? $startedAt,
                    ]);
                }

                $attemptCount++;
                if ($attemptCount % 50 === 0) {
                    seed_log('Quiz attempts inserted: ' . $attemptCount);
                }
            }

            seed_log_progress('Quizzes processed for attempts', $quizIndex + 1, count($approvedQuizzes), 5);
        }
        $stats['quiz_attempts'] = $attemptCount;

        $kuppiTimetableSlots = [
            [1, '08:00:00', '10:00:00', 'IS1201 Programming Lecture'],
            [1, '14:00:00', '16:00:00', 'IS1210 Database Lecture'],
            [2, '09:00:00', '11:00:00', 'IS1211 Networks Lecture'],
            [3, '10:00:00', '12:00:00', 'IS1212 Statistics Lecture'],
            [3, '15:00:00', '17:00:00', 'IS2203 OOP Practical'],
            [4, '08:00:00', '10:00:00', 'IS2206 BPM Lecture'],
            [5, '13:00:00', '15:00:00', 'IS2211 UI/UX Studio'],
            [6, '09:00:00', '11:00:00', 'IS2201 Group Project Mentoring'],
        ];

        seed_log('Step 15/18: creating university timetable slots...');
        foreach ($kuppiTimetableSlots as $slot) {
            seed_insert_kuppi_timetable_slot($pdo, [
                'batch_id' => $batchId,
                'day_of_week' => (int) $slot[0],
                'start_time' => $slot[1],
                'end_time' => $slot[2],
                'reason' => $slot[3],
                'created_by_user_id' => (int) $seedUsers['moderators'][0]['id'],
                'updated_by_user_id' => (int) $seedUsers['moderators'][0]['id'],
                'created_at' => seed_random_datetime('-120 days', '-10 days'),
                'updated_at' => seed_random_datetime('-10 days', '-1 days'),
            ]);
        }

        $requestRows = [];
        seed_log('Step 15/18 continued: creating Kuppi requests + voting + conductor applications...');
        for ($r = 0; $r < 36; $r++) {
            $subject = $subjectRows[$r % count($subjectRows)];
            $requesterPool = array_merge($seedUsers['students'], $seedUsers['coordinators']);
            $requester = $requesterPool[$r % count($requesterPool)];
            $status = match ($r % 9) {
                0, 1, 2, 3 => 'open',
                4, 5 => 'scheduled',
                6, 7 => 'completed',
                default => 'cancelled',
            };

            $requestId = seed_insert_kuppi_request($pdo, [
                'batch_id' => $batchId,
                'subject_id' => (int) $subject['id'],
                'requested_by_user_id' => (int) $requester['id'],
                'title' => seed_kuppi_request_title($subject['code'], $subject['name']),
                'description' => 'Focused group session request for ' . $subject['name'] . ' with guided examples and Q&A.',
                'tags_csv' => 'revision,exam-prep,peer-learning',
                'status' => $status,
                'created_at' => seed_random_datetime('-90 days', '-1 days'),
                'updated_at' => seed_random_datetime('-20 days', '-1 days'),
            ]);

            $requestRows[] = [
                'id' => $requestId,
                'subject_id' => (int) $subject['id'],
                'status' => $status,
            ];
            $stats['kuppi_requests']++;

            $votePool = array_merge($seedUsers['students'], $seedUsers['coordinators']);
            $voteCount = $r % 10;
            for ($v = 0; $v < $voteCount; $v++) {
                $voter = $votePool[($r + $v + 3) % count($votePool)];
                if ((int) $voter['id'] === (int) $requester['id']) {
                    continue;
                }

                seed_insert_kuppi_request_vote($pdo, [
                    'request_id' => $requestId,
                    'user_id' => (int) $voter['id'],
                    'vote_type' => ($v % 4 === 0) ? 'down' : 'up',
                    'created_at' => seed_random_datetime('-60 days', '-1 days'),
                    'updated_at' => seed_random_datetime('-12 days', '-1 days'),
                ]);
            }

            if ($status === 'open') {
                $applicationCount = 2 + ($r % 3);
                for ($a = 0; $a < $applicationCount; $a++) {
                    $applicant = $seedUsers['students'][($r * 3 + $a) % count($seedUsers['students'])];
                    $applicationId = seed_insert_kuppi_conductor_application($pdo, [
                        'request_id' => $requestId,
                        'applicant_user_id' => (int) $applicant['id'],
                        'motivation' => 'I can host this session with examples, recap notes, and timed practice.',
                        'availability_csv' => 'weekday-evening,weekend-morning',
                        'created_at' => seed_random_datetime('-40 days', '-1 days'),
                        'updated_at' => seed_random_datetime('-8 days', '-1 days'),
                    ]);

                    $conductorVoteCount = $a + 1;
                    for ($cv = 0; $cv < $conductorVoteCount; $cv++) {
                        $voter = $seedUsers['students'][($r + $cv + 10) % count($seedUsers['students'])];
                        if ((int) $voter['id'] === (int) $applicant['id']) {
                            continue;
                        }

                        seed_insert_kuppi_conductor_vote($pdo, [
                            'application_id' => $applicationId,
                            'voter_user_id' => (int) $voter['id'],
                            'created_at' => seed_random_datetime('-20 days', '-1 days'),
                            'updated_at' => seed_random_datetime('-6 days', '-1 days'),
                        ]);
                    }
                }
            }

            seed_log_progress('Kuppi requests created', $r + 1, 36, 6);
        }

        $scheduledSessionCount = 0;
        seed_log('Step 15/18 continued: creating scheduled sessions + hosts...');
        foreach ($requestRows as $index => $request) {
            if (!in_array($request['status'], ['scheduled', 'completed', 'cancelled'], true)) {
                continue;
            }

            $subject = seed_find_subject_by_id($subjectRows, (int) $request['subject_id']);
            if ($subject === null) {
                continue;
            }

            $isOnline = $index % 2 === 0;
            $startHour = 17 + ($index % 3);
            $endHour = $startHour + 2;
            $sessionDate = seed_random_date(($request['status'] === 'scheduled') ? '+1 day' : '-45 days', ($request['status'] === 'scheduled') ? '+35 days' : '-1 day');

            $creator = $seedUsers['coordinators'][$index % count($seedUsers['coordinators'])];
            $cancelledBy = $request['status'] === 'cancelled' ? (int) $seedUsers['moderators'][0]['id'] : null;
            $cancelledAt = $request['status'] === 'cancelled' ? seed_random_datetime('-14 days', '-1 days') : null;

            $sessionId = seed_insert_kuppi_scheduled_session($pdo, [
                'batch_id' => $batchId,
                'subject_id' => (int) $request['subject_id'],
                'request_id' => (int) $request['id'],
                'title' => $subject['code'] . ' Collaborative Kuppi Session',
                'description' => 'Guided session to resolve difficult concepts and prepare for assessments.',
                'session_date' => $sessionDate,
                'start_time' => sprintf('%02d:00:00', $startHour),
                'end_time' => sprintf('%02d:00:00', $endHour),
                'duration_minutes' => 120,
                'max_attendees' => 20 + ($index % 6) * 5,
                'location_type' => $isOnline ? 'online' : 'physical',
                'location_text' => $isOnline ? null : 'UCSC Study Hall ' . (($index % 4) + 1),
                'meeting_link' => $isOnline ? 'https://meet.google.com/' . seed_random_slug(10) : null,
                'notes' => 'Bring previous tutorial attempts and unresolved questions.',
                'status' => $request['status'],
                'created_by_user_id' => (int) $creator['id'],
                'cancelled_by_user_id' => $cancelledBy,
                'cancelled_at' => $cancelledAt,
                'created_at' => seed_random_datetime('-35 days', '-1 days'),
                'updated_at' => seed_random_datetime('-10 days', '-1 days'),
            ]);
            $scheduledSessionCount++;

            $hostStudent = $seedUsers['students'][($index * 5) % count($seedUsers['students'])];
            seed_insert_kuppi_scheduled_session_host($pdo, [
                'session_id' => $sessionId,
                'host_user_id' => (int) $hostStudent['id'],
                'source_type' => 'manual',
                'source_application_id' => null,
                'assigned_by_user_id' => (int) $creator['id'],
                'created_at' => seed_random_datetime('-30 days', '-1 days'),
                'updated_at' => seed_random_datetime('-7 days', '-1 days'),
            ]);

            $hostCoordinator = $seedUsers['coordinators'][($index + 2) % count($seedUsers['coordinators'])];
            seed_insert_kuppi_scheduled_session_host($pdo, [
                'session_id' => $sessionId,
                'host_user_id' => (int) $hostCoordinator['id'],
                'source_type' => 'manual',
                'source_application_id' => null,
                'assigned_by_user_id' => (int) $creator['id'],
                'created_at' => seed_random_datetime('-30 days', '-1 days'),
                'updated_at' => seed_random_datetime('-7 days', '-1 days'),
            ]);

            if ($scheduledSessionCount % 5 === 0) {
                seed_log('Scheduled sessions inserted: ' . $scheduledSessionCount);
            }
        }
        $stats['kuppi_sessions'] = $scheduledSessionCount;

        $gpaGradeScale = [
            ['A+', 'Excellent', 4.00],
            ['A', 'Excellent', 4.00],
            ['A-', 'Very Good', 3.70],
            ['B+', 'Good', 3.30],
            ['B', 'Good', 3.00],
            ['B-', 'Satisfactory', 2.70],
            ['C+', 'Acceptable', 2.30],
            ['C', 'Acceptable', 2.00],
            ['C-', 'Pass', 1.70],
            ['D', 'Pass', 1.00],
            ['F', 'Fail', 0.00],
        ];

        $gradeMap = [];
        seed_log('Step 16/18: creating GPA grade scale + term records...');
        foreach ($gpaGradeScale as $sort => $gradeRow) {
            seed_insert_gpa_grade_scale($pdo, [
                'batch_id' => $batchId,
                'letter_grade' => $gradeRow[0],
                'description' => $gradeRow[1],
                'grade_point' => $gradeRow[2],
                'sort_order' => $sort + 1,
                'created_by_user_id' => (int) $seedUsers['moderators'][0]['id'],
                'updated_by_user_id' => (int) $seedUsers['moderators'][0]['id'],
                'created_at' => seed_random_datetime('-120 days', '-40 days'),
                'updated_at' => seed_random_datetime('-8 days', '-1 days'),
            ]);
            $gradeMap[$gradeRow[0]] = (float) $gradeRow[2];
            seed_log_progress('GPA grade-scale rows created', $sort + 1, count($gpaGradeScale), 3);
        }

        $subjectsByTerm = [];
        foreach ($subjectRows as $subject) {
            $termKey = $subject['academic_year'] . '-' . $subject['semester'];
            if (!isset($subjectsByTerm[$termKey])) {
                $subjectsByTerm[$termKey] = [];
            }
            $subjectsByTerm[$termKey][] = $subject;
        }

        $termRecordCount = 0;
        foreach ($seedUsers['students'] as $index => $student) {
            $terms = [['1', '1'], ['1', '2']];
            if ((int) $student['academic_year'] >= 2) {
                $terms[] = ['2', '1'];
            }

            foreach ($terms as $term) {
                $year = (int) $term[0];
                $semester = (int) $term[1];
                $termKey = $year . '-' . $semester;
                $termSubjects = array_values(array_filter(
                    $subjectsByTerm[$termKey] ?? [],
                    static fn(array $subject): bool => !$subject['is_non_gpa']
                ));

                if (empty($termSubjects)) {
                    continue;
                }

                $entries = [];
                $totalCredits = 0.0;
                $totalQuality = 0.0;

                foreach ($termSubjects as $subject) {
                    $letter = seed_pick_grade_letter($index + (int) $subject['id']);
                    $gradePoint = (float) ($gradeMap[$letter] ?? 0.0);
                    $credits = (float) $subject['credits'];
                    $quality = round($credits * $gradePoint, 2);

                    $entries[] = [
                        'subject_id' => (int) $subject['id'],
                        'subject_name_snapshot' => $subject['name'],
                        'credit_value' => $credits,
                        'letter_grade' => $letter,
                        'grade_point_snapshot' => $gradePoint,
                        'quality_points' => $quality,
                    ];

                    $totalCredits += $credits;
                    $totalQuality += $quality;
                }

                if ($totalCredits <= 0) {
                    continue;
                }

                $semesterGpa = round($totalQuality / $totalCredits, 2);
                $recordId = seed_insert_gpa_term_record($pdo, [
                    'user_id' => (int) $student['id'],
                    'batch_id' => $batchId,
                    'academic_year' => $year,
                    'semester' => $semester,
                    'semester_gpa' => $semesterGpa,
                    'total_credits' => round($totalCredits, 2),
                    'graded_subject_count' => count($entries),
                    'created_at' => seed_random_datetime('-100 days', '-10 days'),
                    'updated_at' => seed_random_datetime('-6 days', '-1 days'),
                ]);

                foreach ($entries as $entry) {
                    seed_insert_gpa_term_subject_entry($pdo, [
                        'term_record_id' => $recordId,
                        'subject_id' => $entry['subject_id'],
                        'subject_name_snapshot' => $entry['subject_name_snapshot'],
                        'credit_value' => $entry['credit_value'],
                        'letter_grade' => $entry['letter_grade'],
                        'grade_point_snapshot' => $entry['grade_point_snapshot'],
                        'quality_points' => $entry['quality_points'],
                        'created_at' => seed_random_datetime('-90 days', '-8 days'),
                        'updated_at' => seed_random_datetime('-5 days', '-1 days'),
                    ]);
                }

                $termRecordCount++;
            }

            seed_log_progress('Students processed for GPA', $index + 1, count($seedUsers['students']), 10);
        }
        $stats['gpa_term_records'] = $termRecordCount;

        $commentCount = 0;
        seed_log('Step 17/18: creating comments across resources/posts/kuppi/quizzes...');
        foreach (array_slice($publishedResourceRows, 0, 70) as $index => $resource) {
            $author = $seedUsers['students'][$index % count($seedUsers['students'])];
            $commentId = seed_insert_comment($pdo, [
                'target_type' => 'resource',
                'target_id' => (int) $resource['id'],
                'parent_comment_id' => null,
                'depth' => 0,
                'user_id' => (int) $author['id'],
                'body' => 'This resource is very useful for understanding the topic. Thanks for sharing!',
                'created_at' => seed_random_datetime('-18 days', '-1 days'),
                'updated_at' => seed_random_datetime('-3 days', '-1 days'),
            ]);
            $commentCount++;

            if ($index % 2 === 0) {
                $replier = $seedUsers['coordinators'][$index % count($seedUsers['coordinators'])];
                seed_insert_comment($pdo, [
                    'target_type' => 'resource',
                    'target_id' => (int) $resource['id'],
                    'parent_comment_id' => $commentId,
                    'depth' => 1,
                    'user_id' => (int) $replier['id'],
                    'body' => 'Glad it helped. Try the tutorial sheet next for extra practice.',
                    'created_at' => seed_random_datetime('-15 days', '-1 days'),
                    'updated_at' => seed_random_datetime('-2 days', '-1 days'),
                ]);
                $commentCount++;
            }

            seed_log_progress('Resource comment threads created', $index + 1, min(70, count($publishedResourceRows)), 10);
        }

        foreach (array_slice($feedPostRows, 0, 60) as $index => $post) {
            $author = $seedUsers['students'][($index + 4) % count($seedUsers['students'])];
            $postCommentId = seed_insert_comment($pdo, [
                'target_type' => 'post',
                'target_id' => (int) $post['id'],
                'parent_comment_id' => null,
                'depth' => 0,
                'user_id' => (int) $author['id'],
                'body' => 'Following this thread. Sharing my notes after today\'s study session.',
                'created_at' => seed_random_datetime('-16 days', '-1 days'),
                'updated_at' => seed_random_datetime('-2 days', '-1 days'),
            ]);
            $commentCount++;

            if ($index % 3 === 0) {
                $replyAuthor = $seedUsers['coordinators'][$index % count($seedUsers['coordinators'])];
                seed_insert_comment($pdo, [
                    'target_type' => 'post',
                    'target_id' => (int) $post['id'],
                    'parent_comment_id' => $postCommentId,
                    'depth' => 1,
                    'user_id' => (int) $replyAuthor['id'],
                    'body' => 'Good point. I\'ll share a guided approach in tonight\'s discussion.',
                    'created_at' => seed_random_datetime('-14 days', '-1 days'),
                    'updated_at' => seed_random_datetime('-2 days', '-1 days'),
                ]);
                $commentCount++;
            }

            seed_log_progress('Feed comment threads created', $index + 1, min(60, count($feedPostRows)), 10);
        }

        foreach (array_slice($requestRows, 0, 24) as $index => $request) {
            $commenter = $seedUsers['students'][($index * 2) % count($seedUsers['students'])];
            seed_insert_comment($pdo, [
                'target_type' => 'kuppi_request',
                'target_id' => (int) $request['id'],
                'parent_comment_id' => null,
                'depth' => 0,
                'user_id' => (int) $commenter['id'],
                'body' => 'I\'m interested in joining this session. Please include problem-solving examples.',
                'created_at' => seed_random_datetime('-12 days', '-1 days'),
                'updated_at' => seed_random_datetime('-2 days', '-1 days'),
            ]);
            $commentCount++;
            seed_log_progress('Kuppi request comments created', $index + 1, min(24, count($requestRows)), 8);
        }

        foreach (array_slice($approvedQuizzes, 0, 18) as $index => $quiz) {
            $commenter = $seedUsers['students'][($index + 6) % count($seedUsers['students'])];
            seed_insert_comment($pdo, [
                'target_type' => 'quiz',
                'target_id' => (int) $quiz['id'],
                'parent_comment_id' => null,
                'depth' => 0,
                'user_id' => (int) $commenter['id'],
                'body' => 'This quiz is well structured. Can we get one more section focused on tricky edge cases?',
                'created_at' => seed_random_datetime('-10 days', '-1 days'),
                'updated_at' => seed_random_datetime('-2 days', '-1 days'),
            ]);
            $commentCount++;

            $firstQuestion = $quiz['questions'][0] ?? null;
            if ($firstQuestion !== null) {
                seed_insert_comment($pdo, [
                    'target_type' => 'quiz_question',
                    'target_id' => (int) $firstQuestion['id'],
                    'parent_comment_id' => null,
                    'depth' => 0,
                    'user_id' => (int) $seedUsers['coordinators'][$index % count($seedUsers['coordinators'])]['id'],
                    'body' => 'Tip: read the key phrase in the question stem before checking the options.',
                    'created_at' => seed_random_datetime('-8 days', '-1 days'),
                    'updated_at' => seed_random_datetime('-1 days', '-1 days'),
                ]);
                $commentCount++;
            }

            seed_log_progress('Quiz comments created', $index + 1, min(18, count($approvedQuizzes)), 6);
        }
        $stats['comments'] = $commentCount;

        $reportTargets = array_slice($feedPostRows, 0, 6);
        seed_log('Step 18/18: creating moderation reports + committing transaction...');
        foreach ($reportTargets as $index => $post) {
            $reporter = $seedUsers['students'][($index + 12) % count($seedUsers['students'])];
            seed_insert_feed_report($pdo, [
                'batch_id' => $batchId,
                'target_type' => 'post',
                'target_id' => (int) $post['id'],
                'reporter_user_id' => (int) $reporter['id'],
                'reason' => ($index % 2 === 0) ? 'other' : 'misinformation',
                'details' => 'Please review this post for correctness and relevance.',
                'status' => ($index % 3 === 0) ? 'resolved' : 'open',
                'reviewed_by_user_id' => ($index % 3 === 0) ? (int) $seedUsers['moderators'][0]['id'] : null,
                'reviewed_at' => ($index % 3 === 0) ? seed_random_datetime('-4 days', '-1 days') : null,
                'action_taken' => ($index % 3 === 0) ? 'noted' : null,
                'created_at' => seed_random_datetime('-10 days', '-1 days'),
                'updated_at' => seed_random_datetime('-2 days', '-1 days'),
            ]);
            seed_log_progress('Feed reports created', $index + 1, count($reportTargets), 2);
        }

        $pdo->commit();
        seed_log(sprintf('Transaction committed in %.2fs.', microtime(true) - $txStart));

        $stats['users'] += 1; // admin user ensured

        seed_log(sprintf('All seeding steps finished in %.2fs.', microtime(true) - $seedStartedAt));

        return $stats;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function seed_connect(string $basePath): PDO
{
    $config = require $basePath . '/config/database.php';

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );

    try {
        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException('Database connection failed: ' . $e->getMessage());
    }
}

function seed_clean_previous_dataset(PDO $pdo, string $basePath): void
{
    seed_log('Cleaning previous IS21 dataset (if exists)...');

    $existingBatchId = (int) seed_fetch_value(
        $pdo,
        'SELECT id FROM batches WHERE batch_code = ? LIMIT 1',
        [SEED_BATCH_CODE]
    );

    if ($existingBatchId > 0) {
        $stmt = $pdo->prepare('DELETE FROM batches WHERE id = ?');
        $stmt->execute([$existingBatchId]);
    }

    $stmtUsers = $pdo->prepare('DELETE FROM users WHERE email LIKE ?');
    $stmtUsers->execute(['%@' . SEED_EMAIL_DOMAIN]);

    $seedStoragePath = $basePath . '/' . SEED_STORAGE_PREFIX;
    if (is_dir($seedStoragePath)) {
        seed_delete_directory_recursive($seedStoragePath);
    }
}

function seed_ensure_admin_user(PDO $pdo): int
{
    $adminId = (int) seed_fetch_value(
        $pdo,
        'SELECT id FROM users WHERE email = ? LIMIT 1',
        ['admin@uninest.com']
    );

    if ($adminId > 0) {
        $stmt = $pdo->prepare(
            'UPDATE users
             SET role = ?, password = ?, university_id = NULL, batch_id = NULL, first_approved_batch_id = NULL
             WHERE id = ?'
        );
        $stmt->execute(['admin', SEED_PASSWORD_HASH_123, $adminId]);

        return $adminId;
    }

    return seed_insert_user($pdo, [
        'name' => 'Uninest Admin',
        'email' => 'admin@uninest.com',
        'role' => 'admin',
        'academic_year' => null,
        'university_id' => null,
        'batch_id' => null,
        'first_approved_batch_id' => null,
        'created_at' => seed_random_datetime('-700 days', '-600 days'),
        'updated_at' => seed_random_datetime('-5 days', '-1 days'),
    ]);
}

function seed_ensure_ucsc_university(PDO $pdo): int
{
    $existing = (int) seed_fetch_value(
        $pdo,
        'SELECT id FROM universities WHERE short_code = ? LIMIT 1',
        ['UCSC']
    );

    if ($existing > 0) {
        $stmt = $pdo->prepare('UPDATE universities SET name = ?, is_active = 1 WHERE id = ?');
        $stmt->execute(['University of Colombo School of Computing', $existing]);
        return $existing;
    }

    $stmtInsert = $pdo->prepare(
        'INSERT INTO universities (name, short_code, is_active, created_at, updated_at)
         VALUES (?, ?, 1, ?, ?)'
    );
    $createdAt = seed_random_datetime('-700 days', '-650 days');
    $updatedAt = seed_random_datetime('-7 days', '-1 days');
    $stmtInsert->execute([
        'University of Colombo School of Computing',
        'UCSC',
        $createdAt,
        $updatedAt,
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_is21_subject_catalog(): array
{
    return [
        ['code' => 'IS1201', 'name' => 'Programming and Problem Solving', 'credits' => 3, 'academic_year' => 1, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Introduction to problem-solving methods, algorithmic thinking, and structured programming.'],
        ['code' => 'IS1202', 'name' => 'Computer Systems', 'credits' => 2, 'academic_year' => 1, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Core principles of computer architecture, operating systems, and hardware concepts.'],
        ['code' => 'IS1203', 'name' => 'Foundations of Information Systems', 'credits' => 2, 'academic_year' => 1, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Foundational concepts of information systems and their role in organizations.'],
        ['code' => 'IS1204', 'name' => 'Fundamentals of Software Engineering', 'credits' => 2, 'academic_year' => 1, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Software process, requirements, design, and quality fundamentals.'],
        ['code' => 'IS1205', 'name' => 'Introduction to Management', 'credits' => 2, 'academic_year' => 1, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Management concepts relevant to IT teams, projects, and organizations.'],
        ['code' => 'IS1206', 'name' => 'Mathematics for Computing', 'credits' => 2, 'academic_year' => 1, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Discrete mathematics and computational math foundations for IS students.'],
        ['code' => 'IS1207', 'name' => 'Internet and Web Technologies', 'credits' => 3, 'academic_year' => 1, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Web architecture, front-end basics, and internet technology foundations.'],
        ['code' => 'EN1201', 'name' => 'Communication Skills', 'credits' => 1, 'academic_year' => 1, 'semester' => 1, 'is_non_gpa' => true, 'description' => 'Academic and professional communication skills for university learners.'],
        ['code' => 'EN1202', 'name' => 'Application Laboratory', 'credits' => 1, 'academic_year' => 1, 'semester' => 1, 'is_non_gpa' => true, 'description' => 'Hands-on application lab focused on productivity and digital workflows.'],

        ['code' => 'IS1208', 'name' => 'Systems Analysis and Design', 'credits' => 2, 'academic_year' => 1, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'Requirements analysis, UML modeling, and system design techniques.'],
        ['code' => 'IS1209', 'name' => 'Information Technology Project Management', 'credits' => 2, 'academic_year' => 1, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'Project lifecycle planning, scope, estimation, risk, and stakeholder communication.'],
        ['code' => 'IS1210', 'name' => 'Database Systems', 'credits' => 3, 'academic_year' => 1, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'Database design, SQL, normalization, transactions, and data modeling.'],
        ['code' => 'IS1211', 'name' => 'Computer Networks', 'credits' => 3, 'academic_year' => 1, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'Network layers, protocols, routing, transport, and practical networking concepts.'],
        ['code' => 'IS1212', 'name' => 'Probability and Statistics', 'credits' => 3, 'academic_year' => 1, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'Probability models, distributions, inference, and statistical reasoning for IS.'],
        ['code' => 'IS1213', 'name' => 'Organizational Behavior', 'credits' => 2, 'academic_year' => 1, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'Behavioral dynamics in organizations, leadership, teams, and motivation.'],
        ['code' => 'IS1214', 'name' => 'Data Structures and Algorithms', 'credits' => 3, 'academic_year' => 1, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'Data structures, algorithm design, complexity, and practical implementation patterns.'],
        ['code' => 'EN1203', 'name' => 'Aesthetic Studies', 'credits' => 1, 'academic_year' => 1, 'semester' => 2, 'is_non_gpa' => true, 'description' => 'Broad-based appreciation of arts and culture to complement academic development.'],

        ['code' => 'IS2201', 'name' => 'Group Project', 'credits' => 4, 'academic_year' => 2, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Team-based project integrating analysis, implementation, and delivery practices.'],
        ['code' => 'IS2202', 'name' => 'Advanced Data Structures and Algorithms', 'credits' => 2, 'academic_year' => 2, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Advanced algorithmic paradigms and optimized data-structure strategies.'],
        ['code' => 'IS2203', 'name' => 'Object Oriented Programming', 'credits' => 3, 'academic_year' => 2, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Object-oriented design principles, patterns, and implementation in modern languages.'],
        ['code' => 'IS2204', 'name' => 'Information Systems Security', 'credits' => 2, 'academic_year' => 2, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Security principles, threats, controls, and governance in information systems.'],
        ['code' => 'IS2205', 'name' => 'Mobile Application Design and Development', 'credits' => 3, 'academic_year' => 2, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Mobile UX and application development workflows for modern platforms.'],
        ['code' => 'IS2206', 'name' => 'Business Process Management', 'credits' => 3, 'academic_year' => 2, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Process analysis, modeling, automation, and optimization for enterprises.'],
        ['code' => 'IS2207', 'name' => 'Electronics and Physical Computing', 'credits' => 3, 'academic_year' => 2, 'semester' => 1, 'is_non_gpa' => false, 'description' => 'Electronics fundamentals with physical computing and IoT prototyping concepts.'],

        ['code' => 'IS2208', 'name' => 'Information Systems Management and Strategy', 'credits' => 2, 'academic_year' => 2, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'Strategic alignment of information systems with business goals and governance.'],
        ['code' => 'IS2209', 'name' => 'Data Management and Governance', 'credits' => 4, 'academic_year' => 2, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'Enterprise data lifecycle, governance frameworks, quality, and stewardship.'],
        ['code' => 'IS2210', 'name' => 'Applied Data Science', 'credits' => 3, 'academic_year' => 2, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'Applied analytics, ML workflows, and data-driven decision-making patterns.'],
        ['code' => 'IS2211', 'name' => 'UI/UX Design', 'credits' => 3, 'academic_year' => 2, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'User interface and experience design methods, prototyping, and usability testing.'],
        ['code' => 'IS2212', 'name' => 'Cloud Infrastructure and Applications', 'credits' => 2, 'academic_year' => 2, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'Cloud deployment models, infrastructure basics, and cloud-native app patterns.'],
        ['code' => 'EN2201', 'name' => 'Entrepreneurship', 'credits' => 2, 'academic_year' => 2, 'semester' => 2, 'is_non_gpa' => false, 'description' => 'Entrepreneurial thinking, opportunity analysis, and startup planning basics.'],
    ];
}

function seed_topic_templates_for_subject(string $subjectCode, string $subjectName): array
{
    return match ($subjectCode) {
        'IS1208' => [
            ['key' => 'foundations', 'title' => 'SDLC & Requirement Analysis', 'description' => 'Lifecycle planning, requirement capture, and business context framing.'],
            ['key' => 'concepts', 'title' => 'UML & System Modeling', 'description' => 'Use cases, class diagrams, sequence flows, and model interpretation.'],
            ['key' => 'practice', 'title' => 'Case Studies & Applied Design', 'description' => 'Applied system analysis scenarios and practical model building exercises.'],
            ['key' => 'exam', 'title' => 'Past Papers & Revision', 'description' => 'Past-paper style questions and focused revision notes.'],
            ['key' => 'reference', 'title' => 'Supplementary References', 'description' => 'Additional reading, handbooks, and reference documents.'],
        ],
        'IS1209' => [
            ['key' => 'foundations', 'title' => 'Project Management Fundamentals', 'description' => 'Core PM concepts, project lifecycles, and foundational definitions.'],
            ['key' => 'concepts', 'title' => 'Scope, Time, Cost & Risk', 'description' => 'Estimation, scheduling, budgeting, and risk-management material.'],
            ['key' => 'practice', 'title' => 'Tools, Calculations & Tutorials', 'description' => 'Practical examples, worked calculations, and tutorial-oriented resources.'],
            ['key' => 'exam', 'title' => 'Assessment Preparation', 'description' => 'Revision-focused summaries and assessment preparation material.'],
            ['key' => 'reference', 'title' => 'Frameworks & Standards', 'description' => 'PMBOK and related governance/reference material.'],
        ],
        'IS1210' => [
            ['key' => 'foundations', 'title' => 'Database Fundamentals', 'description' => 'Introductory database concepts, architecture, and relational basics.'],
            ['key' => 'concepts', 'title' => 'Relational Design & SQL Concepts', 'description' => 'Schema modeling, SQL theory, and relational operations.'],
            ['key' => 'practice', 'title' => 'SQL Practice & Lab Work', 'description' => 'Hands-on SQL tasks, worked examples, and lab-oriented practice.'],
            ['key' => 'exam', 'title' => 'Normalization & Exam Revision', 'description' => 'Normalization, tricky problem patterns, and exam-focused preparation.'],
            ['key' => 'reference', 'title' => 'Reference Materials', 'description' => 'Detailed notes and supplementary database references.'],
        ],
        'IS1211' => [
            ['key' => 'foundations', 'title' => 'Networking Fundamentals', 'description' => 'OSI/TCP-IP basics, network components, and addressing essentials.'],
            ['key' => 'concepts', 'title' => 'Protocols & Architecture', 'description' => 'Layer-specific protocol behavior and architecture-level understanding.'],
            ['key' => 'practice', 'title' => 'Tutorials & Problem Solving', 'description' => 'Worked networking questions and practical explanation resources.'],
            ['key' => 'exam', 'title' => 'Past Paper Revision', 'description' => 'Exam-style preparation and revision packs for networking topics.'],
            ['key' => 'reference', 'title' => 'Reference Books & Notes', 'description' => 'Comprehensive notes and textbook-style references.'],
        ],
        'IS1212' => [
            ['key' => 'foundations', 'title' => 'Probability Foundations', 'description' => 'Probability rules, basic models, and introductory statistical ideas.'],
            ['key' => 'concepts', 'title' => 'Distributions & Statistical Concepts', 'description' => 'Discrete/continuous distributions and related analytical concepts.'],
            ['key' => 'practice', 'title' => 'Tutorial Questions & Worked Answers', 'description' => 'Tutorial sheets, solved examples, and applied exercises.'],
            ['key' => 'exam', 'title' => 'Revision & Exam Preparation', 'description' => 'Exam-focused statistics notes and revision-oriented material.'],
            ['key' => 'reference', 'title' => 'Supplementary Notes', 'description' => 'Additional notes and reference handouts.'],
        ],
        'IS1213' => [
            ['key' => 'foundations', 'title' => 'OB Fundamentals', 'description' => 'Core organizational behavior principles and foundational models.'],
            ['key' => 'concepts', 'title' => 'Motivation, Emotions & Decision Making', 'description' => 'Behavioral and cognitive dimensions in organizational settings.'],
            ['key' => 'practice', 'title' => 'Teams, Leadership & Applied Cases', 'description' => 'Team dynamics, leadership-focused materials, and practical case content.'],
            ['key' => 'exam', 'title' => 'Revision & Assessment Support', 'description' => 'Assessment-aligned revision resources for OB topics.'],
            ['key' => 'reference', 'title' => 'Additional References', 'description' => 'Supplementary slides and reading material.'],
        ],
        'IS1214' => [
            ['key' => 'foundations', 'title' => 'Core Data Structures', 'description' => 'Foundational structures, memory models, and implementation basics.'],
            ['key' => 'concepts', 'title' => 'Algorithm Analysis & Design', 'description' => 'Complexity, algorithmic design patterns, and analysis techniques.'],
            ['key' => 'practice', 'title' => 'Tutorials, Practicals & Coding Drills', 'description' => 'Tutorials and practical problem-solving exercises.'],
            ['key' => 'exam', 'title' => 'Revision & Exam Drills', 'description' => 'Exam preparation packs and quick revision references.'],
            ['key' => 'reference', 'title' => 'Short Notes & References', 'description' => 'Condensed notes and supplementary references for DSA.'],
        ],
        default => [
            ['key' => 'foundations', 'title' => $subjectName . ' Foundations', 'description' => 'Core foundations and introductory material.'],
            ['key' => 'concepts', 'title' => $subjectName . ' Concepts & Theory', 'description' => 'Theory-driven concepts and essential explanatory notes.'],
            ['key' => 'practice', 'title' => $subjectName . ' Tutorials & Practice', 'description' => 'Practice-oriented resources and tutorial-style references.'],
            ['key' => 'exam', 'title' => $subjectName . ' Assessments & Revision', 'description' => 'Revision support and exam readiness resources.'],
            ['key' => 'reference', 'title' => $subjectName . ' Reference Library', 'description' => 'Supplementary reading and reference material.'],
        ],
    };
}

function seed_pick_topic_for_asset(array $topics, array $assetMeta): ?array
{
    if (empty($topics)) {
        return null;
    }

    $sourcePath = (string) ($assetMeta['source_path'] ?? '');
    $relativePath = (string) ($assetMeta['relative_path'] ?? '');
    $sourceGroup = strtolower((string) ($assetMeta['source_group'] ?? 'subject_material'));
    $fileName = strtolower((string) ($assetMeta['file_name'] ?? basename($sourcePath)));
    $pathContext = strtolower(trim($fileName . ' ' . $relativePath . ' ' . $sourcePath));
    $bucket = seed_resource_bucket_for_asset($pathContext, $sourceGroup);

    foreach ($topics as $topic) {
        if ((string) ($topic['key'] ?? '') === $bucket) {
            return $topic;
        }
    }

    foreach ($topics as $topic) {
        if ((string) ($topic['key'] ?? '') === 'reference') {
            return $topic;
        }
    }

    return $topics[0] ?? null;
}

function seed_resource_bucket_for_asset(string $fileName, string $sourceGroup): string
{
    if ($sourceGroup === 'past_papers') {
        return 'exam';
    }
    if ($sourceGroup === 'short_notes') {
        return 'reference';
    }
    if ($sourceGroup === 'practice_material') {
        return 'practice';
    }

    $name = strtolower($fileName);

    if (seed_string_contains_any($name, ['past', 'sample', 'exam', 'revision'])) {
        return 'exam';
    }

    if (seed_string_contains_any($name, ['tutorial', 'tute', 'practical', 'lab', 'worksheet', 'challenge'])) {
        return 'practice';
    }

    if (seed_string_contains_any($name, ['introduction', 'intro', 'fundamentals', 'syllabus', 'lesson 1', 'lec 1', 'l1-'])) {
        return 'foundations';
    }

    if (seed_string_contains_any($name, ['book', 'module', 'pmbok', 'complete', 'note', 'handout', 'slides'])) {
        return 'reference';
    }

    return 'concepts';
}

function seed_resource_link_category_for_topic_key(string $topicKey): string
{
    return match ($topicKey) {
        'practice' => 'Tutorials',
        'exam' => 'Past Papers',
        'reference' => 'Reference Materials',
        default => 'Video Tutorials',
    };
}

function seed_generate_sri_lankan_student_names(int $count): array
{
    $firstNames = [
        'Akalanka', 'Amaya', 'Anudi', 'Asela', 'Avishka', 'Binara', 'Bhagya', 'Chamod', 'Chamari', 'Chathura',
        'Dasuni', 'Dilshan', 'Dinesha', 'Dinuka', 'Dulani', 'Erandi', 'Gayan', 'Gayani', 'Hasini', 'Heshan',
        'Ishara', 'Ishani', 'Janaka', 'Janani', 'Kamal', 'Kasuni', 'Kavindu', 'Kavisha', 'Lahiru', 'Lakmini',
        'Madusha', 'Maheshi', 'Malith', 'Manuri', 'Nadeesha', 'Nadeeka', 'Naveen', 'Nethmi', 'Nimasha', 'Nipun',
        'Pabodha', 'Piumi', 'Rashmi', 'Ravin', 'Sachini', 'Sachith', 'Sanduni', 'Sanjaya', 'Sathira', 'Sewwandi',
        'Sharanya', 'Shenal', 'Supun', 'Surangi', 'Tharika', 'Tharindu', 'Udesh', 'Uthpala', 'Vihanga', 'Yasas',
        'Yoshitha', 'Zuhaira', 'Aaradhya', 'Keshani', 'Mihiran', 'Navodya', 'Ruwani', 'Savidu', 'Trevin', 'Vishmi',
    ];

    $lastNames = [
        'Perera', 'Fernando', 'Silva', 'Gunawardena', 'Wijesinghe', 'Jayawardena', 'Abeysekera', 'Rajapaksha',
        'Herath', 'Karunaratne', 'Senanayake', 'Bandara', 'Peiris', 'Dissanayake', 'Wijeratne', 'Kodikara',
        'Samarasinghe', 'Ekanayake', 'Amarasinghe', 'Jayasuriya', 'Pathirana', 'Ranasinghe', 'Wickramasinghe',
        'Madushanka', 'De Mel', 'Muthukumaran', 'Sivanesan', 'Yogarajah', 'Arulanantham', 'Nadarajah',
        'Tharmalingam', 'Raveendran', 'Krishnarajah', 'Subramaniam',
    ];

    mt_srand(210021);

    $result = [];
    $seen = [];
    while (count($result) < $count) {
        $first = $firstNames[mt_rand(0, count($firstNames) - 1)];
        $last = $lastNames[mt_rand(0, count($lastNames) - 1)];
        $name = $first . ' ' . $last;
        if (isset($seen[$name])) {
            continue;
        }
        $seen[$name] = true;
        $result[] = $name;
    }

    return $result;
}

function seed_collect_resource_assets(string $basePath): array
{
    $assetsRoot = $basePath . '/database/seed_assets/resources';
    if (!is_dir($assetsRoot)) {
        return [];
    }

    $allFiles = seed_scan_seed_files($assetsRoot, 'subject_material', $assetsRoot);

    $pool = ['_generic' => []];
    foreach ($allFiles as $assetMeta) {
        $relativePath = strtolower((string) ($assetMeta['relative_path'] ?? (string) ($assetMeta['file_name'] ?? '')));
        $fileName = strtolower((string) ($assetMeta['file_name'] ?? ''));

        $assetMeta['source_group'] = seed_detect_source_group_for_asset_path($relativePath);
        $subjectCode = seed_detect_subject_code_for_asset_path($relativePath . ' ' . $fileName);
        if ($subjectCode === null) {
            $pool['_generic'][] = $assetMeta;
            continue;
        }

        if (!isset($pool[$subjectCode])) {
            $pool[$subjectCode] = [];
        }
        $pool[$subjectCode][] = $assetMeta;
    }

    foreach ($pool as $code => $items) {
        if (!is_array($items)) {
            continue;
        }
        usort(
            $items,
            static fn(array $a, array $b): int => strcmp(
                (string) ($a['relative_path'] ?? $a['file_name'] ?? ''),
                (string) ($b['relative_path'] ?? $b['file_name'] ?? '')
            )
        );
        $pool[$code] = $items;
    }

    return $pool;
}

function seed_scan_seed_files(string $dirPath, string $sourceGroup, ?string $relativeRoot = null): array
{
    $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'jpg', 'jpeg', 'png'];
    $root = $relativeRoot !== null ? rtrim(str_replace('\\', '/', $relativeRoot), '/') : rtrim(str_replace('\\', '/', $dirPath), '/');

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $entry = (string) $fileInfo->getFilename();
        if ($entry === '' || str_starts_with($entry, '.')) {
            continue;
        }

        $extension = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        $fullPath = str_replace('\\', '/', (string) $fileInfo->getPathname());
        $normalizedRoot = $root . '/';
        $relativePath = str_starts_with($fullPath, $normalizedRoot)
            ? substr($fullPath, strlen($normalizedRoot))
            : basename($fullPath);

        $files[] = [
            'source_path' => $fullPath,
            'file_name' => $entry,
            'relative_path' => (string) $relativePath,
            'source_group' => $sourceGroup,
        ];
    }

    usort(
        $files,
        static fn(array $a, array $b): int => strcmp((string) ($a['file_name'] ?? ''), (string) ($b['file_name'] ?? ''))
    );
    return $files;
}

function seed_detect_source_group_for_asset_path(string $path): string
{
    $normalized = strtolower(str_replace('\\', '/', $path));

    if (seed_string_contains_any($normalized, ['past papers', '/2ndsenpp/', '/past paper', '/pastpaper'])) {
        return 'past_papers';
    }

    if (seed_string_contains_any($normalized, ['short notes', 'shortnotes', 'more-shortnotes', 'short note'])) {
        return 'short_notes';
    }

    if (seed_string_contains_any($normalized, ['practical', 'tutorial', 'lab', 'assignment'])) {
        return 'practice_material';
    }

    return 'subject_material';
}

function seed_detect_subject_code_for_asset_path(string $path): ?string
{
    $normalized = strtoupper(str_replace(['\\', '_'], ['/', ' '], $path));

    if (preg_match('/\bIS\s*[- ]?(\d{4})\b/', $normalized, $matches) === 1) {
        return 'IS' . $matches[1];
    }

    $lower = strtolower(str_replace(['\\', '_', '-'], ['/', ' ', ' '], $path));
    $subjectAliasMap = [
        'IS1208' => ['systems analysis and design', 'system analysis and design', 'sad 1208'],
        'IS1209' => ['project management', 'information technology and management', 'it project management'],
        'IS1210' => ['database systems', 'dbms', 'database'],
        'IS1211' => ['computer networks', 'networking', 'network'],
        'IS1212' => ['probability and statistics', 'probability', 'statistics'],
        'IS1213' => ['organizational behavior', 'organizational behaviour', 'ob '],
        'IS1214' => ['data structures and algorithms', 'data structures', 'algorithms', 'dsa'],
    ];

    foreach ($subjectAliasMap as $subjectCode => $aliases) {
        if (seed_string_contains_any($lower, $aliases)) {
            return $subjectCode;
        }
    }

    return null;
}

function seed_match_subject_for_asset(array $subjectRows, array $assetMeta): ?array
{
    $path = (string) ($assetMeta['relative_path'] ?? $assetMeta['source_path'] ?? $assetMeta['file_name'] ?? '');
    $detectedCode = seed_detect_subject_code_for_asset_path($path);
    if ($detectedCode !== null) {
        foreach ($subjectRows as $subject) {
            if ((string) ($subject['code'] ?? '') === $detectedCode) {
                return $subject;
            }
        }
    }

    if (empty($subjectRows)) {
        return null;
    }

    $isSubjects = array_values(array_filter(
        $subjectRows,
        static fn(array $subject): bool => str_starts_with((string) ($subject['code'] ?? ''), 'IS')
    ));

    if (empty($isSubjects)) {
        return $subjectRows[0] ?? null;
    }

    $hash = crc32($path);
    $index = (int) ($hash % count($isSubjects));
    return $isSubjects[$index] ?? $isSubjects[0];
}

function seed_copy_resource_asset_cached(
    string $basePath,
    array $assetMeta,
    string $subjectCode,
    int &$counter,
    array &$cache
): array {
    $sourcePath = (string) ($assetMeta['source_path'] ?? '');
    if ($sourcePath === '') {
        throw new RuntimeException('Invalid source path for seed asset cache.');
    }

    if (isset($cache[$sourcePath]) && is_array($cache[$sourcePath])) {
        return $cache[$sourcePath];
    }

    $copied = seed_copy_resource_asset($basePath, $assetMeta, $subjectCode, $counter);
    $counter++;
    $cache[$sourcePath] = $copied;

    return $copied;
}

function seed_copy_resource_asset(string $basePath, array $assetMeta, string $subjectCode, int $counter): array
{
    $sourcePath = (string) ($assetMeta['source_path'] ?? '');
    if ($sourcePath === '' || !is_file($sourcePath)) {
        throw new RuntimeException('Seed resource asset not found: ' . $sourcePath);
    }

    $targetDir = $basePath . '/' . SEED_STORAGE_PREFIX . '/assets';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to create seed storage directory: ' . $targetDir);
    }

    $originalName = basename($sourcePath);
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $storedName = sprintf('seed-%s-%04d.%s', strtolower($subjectCode), $counter, $extension);
    $targetPath = $targetDir . '/' . $storedName;

    if (!copy($sourcePath, $targetPath)) {
        throw new RuntimeException('Failed to copy seed asset: ' . $sourcePath);
    }

    $relativePath = SEED_STORAGE_PREFIX . '/assets/' . $storedName;
    $mime = mime_content_type($targetPath);
    if (!is_string($mime) || trim($mime) === '') {
        $mime = 'application/octet-stream';
    }

    return [
        'file_path' => $relativePath,
        'file_name' => $originalName,
        'file_mime' => $mime,
        'file_size' => (int) filesize($targetPath),
    ];
}

function seed_resource_file_title(string $subjectCode, string $fileName): string
{
    $base = preg_replace('/\.[^.]+$/', '', $fileName) ?: $fileName;
    $base = preg_replace('/\s+/', ' ', trim((string) $base));

    return $subjectCode . ' - ' . $base;
}

function seed_resource_category_for_extension(string $fileName): string
{
    $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
    return match ($extension) {
        'pdf' => 'Lecture Notes',
        'doc', 'docx' => 'Short Notes',
        'ppt', 'pptx' => 'Tutorials',
        'xls', 'xlsx' => 'Lab Sheets',
        default => 'Reference Materials',
    };
}

function seed_demo_resource_link(string $subjectCode, int $topicId): string
{
    $encoded = urlencode($subjectCode . ' topic ' . $topicId);
    $links = [
        'https://www.youtube.com/results?search_query=' . $encoded,
        'https://ocw.mit.edu/search/?q=' . $encoded,
        'https://www.geeksforgeeks.org/?s=' . $encoded,
        'https://www.w3schools.com/search/search.asp?q=' . $encoded,
    ];

    return $links[$topicId % count($links)];
}

function seed_announcement_body(string $title, string $subjectName): string
{
    return $title . "\n\n" .
        'Please review this official batch update and adjust your study plan accordingly. ' .
        'If your focus area is ' . $subjectName . ', prioritize this week\'s activities and deadlines.';
}

function seed_feed_post_body(string $postType, ?string $subjectName): string
{
    $context = $subjectName ? (' for ' . $subjectName) : '';

    return match ($postType) {
        'discussion' => 'Starting a focused discussion thread' . $context . '. Share your approach, common mistakes, and shortcuts.',
        'question' => 'Need help understanding a tricky concept' . $context . '. Can someone explain with a simple example?',
        'resource_share' => 'Sharing a useful resource' . $context . ' that helped me revise quickly before tutorials.',
        default => 'Batch update' . $context . ': who is joining tonight\'s collaborative study block?',
    };
}

function seed_kuppi_request_title(string $subjectCode, string $subjectName): string
{
    $templates = [
        'Need a focused revision session for %s',
        '%s problem-solving sprint request',
        'Host a peer-learning slot for %s',
    ];

    $template = $templates[crc32($subjectCode) % count($templates)];
    return sprintf($template, $subjectName . ' (' . $subjectCode . ')');
}

function seed_pick_grade_letter(int $seed): string
{
    $letters = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D', 'F'];
    $weights = [5, 9, 12, 16, 20, 14, 10, 7, 4, 2, 1];

    $total = array_sum($weights);
    $roll = ($seed * 37 + 17) % $total;

    $cursor = 0;
    foreach ($weights as $index => $weight) {
        $cursor += $weight;
        if ($roll < $cursor) {
            return $letters[$index];
        }
    }

    return 'B';
}

function seed_insert_user(PDO $pdo, array $row): int
{
    $sql = 'INSERT INTO users
        (name, email, password, role, academic_year, university_id, batch_id, first_approved_batch_id, created_at, updated_at)
        VALUES (:name, :email, :password, :role, :academic_year, :university_id, :batch_id, :first_approved_batch_id, :created_at, :updated_at)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => $row['name'],
        ':email' => $row['email'],
        ':password' => SEED_PASSWORD_HASH_123,
        ':role' => $row['role'],
        ':academic_year' => $row['academic_year'],
        ':university_id' => $row['university_id'],
        ':batch_id' => $row['batch_id'],
        ':first_approved_batch_id' => $row['first_approved_batch_id'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_update_user_batch(PDO $pdo, int $userId, int $batchId, ?int $firstApprovedBatchId): void
{
    $stmt = $pdo->prepare(
        'UPDATE users
         SET batch_id = ?, first_approved_batch_id = COALESCE(first_approved_batch_id, ?)
         WHERE id = ?'
    );
    $stmt->execute([$batchId, $firstApprovedBatchId, $userId]);
}

function seed_insert_batch(PDO $pdo, array $row): int
{
    $sql = 'INSERT INTO batches
        (batch_code, name, program, intake_year, university_id, moderator_user_id, status, rejection_reason, reviewed_by, reviewed_at, created_at, updated_at)
        VALUES (:batch_code, :name, :program, :intake_year, :university_id, :moderator_user_id, :status, :rejection_reason, :reviewed_by, :reviewed_at, :created_at, :updated_at)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':batch_code' => $row['batch_code'],
        ':name' => $row['name'],
        ':program' => $row['program'],
        ':intake_year' => $row['intake_year'],
        ':university_id' => $row['university_id'],
        ':moderator_user_id' => $row['moderator_user_id'],
        ':status' => $row['status'],
        ':rejection_reason' => $row['rejection_reason'],
        ':reviewed_by' => $row['reviewed_by'],
        ':reviewed_at' => $row['reviewed_at'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_student_batch_request(PDO $pdo, array $row): int
{
    $sql = 'INSERT INTO student_batch_requests
        (student_user_id, requested_batch_id, status, rejection_reason, reviewed_by, reviewed_role, reviewed_at, created_at, updated_at)
        VALUES (:student_user_id, :requested_batch_id, :status, :rejection_reason, :reviewed_by, :reviewed_role, :reviewed_at, :created_at, :updated_at)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':student_user_id' => $row['student_user_id'],
        ':requested_batch_id' => $row['requested_batch_id'],
        ':status' => $row['status'],
        ':rejection_reason' => $row['rejection_reason'],
        ':reviewed_by' => $row['reviewed_by'],
        ':reviewed_role' => $row['reviewed_role'],
        ':reviewed_at' => $row['reviewed_at'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_subject(PDO $pdo, array $row): int
{
    $sql = 'INSERT INTO subjects
        (batch_id, code, name, description, credits, academic_year, semester, created_by, created_at, updated_at)
        VALUES (:batch_id, :code, :name, :description, :credits, :academic_year, :semester, :created_by, :created_at, :updated_at)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':batch_id' => $row['batch_id'],
        ':code' => $row['code'],
        ':name' => $row['name'],
        ':description' => $row['description'],
        ':credits' => $row['credits'],
        ':academic_year' => $row['academic_year'],
        ':semester' => $row['semester'],
        ':created_by' => $row['created_by'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_subject_coordinator(PDO $pdo, array $row): int
{
    $sql = 'INSERT INTO subject_coordinators (subject_id, student_user_id, assigned_by, created_at)
            VALUES (:subject_id, :student_user_id, :assigned_by, :created_at)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':subject_id' => $row['subject_id'],
        ':student_user_id' => $row['student_user_id'],
        ':assigned_by' => $row['assigned_by'],
        ':created_at' => $row['created_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_topic(PDO $pdo, array $row): int
{
    $sql = 'INSERT INTO topics (subject_id, title, description, sort_order, created_by, created_at, updated_at)
            VALUES (:subject_id, :title, :description, :sort_order, :created_by, :created_at, :updated_at)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':subject_id' => $row['subject_id'],
        ':title' => $row['title'],
        ':description' => $row['description'],
        ':sort_order' => $row['sort_order'],
        ':created_by' => $row['created_by'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_resource(PDO $pdo, array $row): int
{
    $sql = 'INSERT INTO resources
        (topic_id, uploaded_by_user_id, title, description, category, category_other, source_type, file_path, file_name, file_mime, file_size, external_url, status, rejection_reason, reviewed_by_user_id, reviewed_at, created_at, updated_at)
        VALUES
        (:topic_id, :uploaded_by_user_id, :title, :description, :category, :category_other, :source_type, :file_path, :file_name, :file_mime, :file_size, :external_url, :status, :rejection_reason, :reviewed_by_user_id, :reviewed_at, :created_at, :updated_at)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':topic_id' => $row['topic_id'],
        ':uploaded_by_user_id' => $row['uploaded_by_user_id'],
        ':title' => $row['title'],
        ':description' => $row['description'],
        ':category' => $row['category'],
        ':category_other' => $row['category_other'],
        ':source_type' => $row['source_type'],
        ':file_path' => $row['file_path'],
        ':file_name' => $row['file_name'],
        ':file_mime' => $row['file_mime'],
        ':file_size' => $row['file_size'],
        ':external_url' => $row['external_url'],
        ':status' => $row['status'],
        ':rejection_reason' => $row['rejection_reason'],
        ':reviewed_by_user_id' => $row['reviewed_by_user_id'],
        ':reviewed_at' => $row['reviewed_at'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_resource_update_request(PDO $pdo, array $row): int
{
    $sql = 'INSERT INTO resource_update_requests
        (resource_id, requested_by_user_id, title, description, category, category_other, source_type, file_path, file_name, file_mime, file_size, external_url, status, rejection_reason, reviewed_by_user_id, reviewed_at, created_at, updated_at)
        VALUES
        (:resource_id, :requested_by_user_id, :title, :description, :category, :category_other, :source_type, :file_path, :file_name, :file_mime, :file_size, :external_url, :status, :rejection_reason, :reviewed_by_user_id, :reviewed_at, :created_at, :updated_at)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':resource_id' => $row['resource_id'],
        ':requested_by_user_id' => $row['requested_by_user_id'],
        ':title' => $row['title'],
        ':description' => $row['description'],
        ':category' => $row['category'],
        ':category_other' => $row['category_other'],
        ':source_type' => $row['source_type'],
        ':file_path' => $row['file_path'],
        ':file_name' => $row['file_name'],
        ':file_mime' => $row['file_mime'],
        ':file_size' => $row['file_size'],
        ':external_url' => $row['external_url'],
        ':status' => $row['status'],
        ':rejection_reason' => $row['rejection_reason'],
        ':reviewed_by_user_id' => $row['reviewed_by_user_id'],
        ':reviewed_at' => $row['reviewed_at'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_resource_rating(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO resource_ratings (resource_id, student_user_id, rating, created_at, updated_at)
         VALUES (:resource_id, :student_user_id, :rating, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':resource_id' => $row['resource_id'],
        ':student_user_id' => $row['student_user_id'],
        ':rating' => $row['rating'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_resource_save(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO resource_saves (resource_id, user_id, created_at)
         VALUES (:resource_id, :user_id, :created_at)'
    );
    $stmt->execute([
        ':resource_id' => $row['resource_id'],
        ':user_id' => $row['user_id'],
        ':created_at' => $row['created_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_announcement(PDO $pdo, array $row): int
{
    $sql = 'INSERT INTO announcements
        (batch_id, subject_id, author_user_id, title, body, is_pinned, pinned_by_user_id, pinned_at, created_at, updated_at)
        VALUES
        (:batch_id, :subject_id, :author_user_id, :title, :body, :is_pinned, :pinned_by_user_id, :pinned_at, :created_at, :updated_at)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':batch_id' => $row['batch_id'],
        ':subject_id' => $row['subject_id'],
        ':author_user_id' => $row['author_user_id'],
        ':title' => $row['title'],
        ':body' => $row['body'],
        ':is_pinned' => $row['is_pinned'],
        ':pinned_by_user_id' => $row['pinned_by_user_id'],
        ':pinned_at' => $row['pinned_at'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_feed_post(PDO $pdo, array $row): int
{
    $sql = 'INSERT INTO feed_posts
        (batch_id, subject_id, author_user_id, post_type, body, image_path, image_name, image_mime, image_size, is_pinned, pinned_by_user_id, pinned_at, is_resolved, resolved_by_user_id, resolved_at, edited_at, created_at, updated_at)
        VALUES
        (:batch_id, :subject_id, :author_user_id, :post_type, :body, :image_path, :image_name, :image_mime, :image_size, :is_pinned, :pinned_by_user_id, :pinned_at, :is_resolved, :resolved_by_user_id, :resolved_at, :edited_at, :created_at, :updated_at)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':batch_id' => $row['batch_id'],
        ':subject_id' => $row['subject_id'],
        ':author_user_id' => $row['author_user_id'],
        ':post_type' => $row['post_type'],
        ':body' => $row['body'],
        ':image_path' => $row['image_path'],
        ':image_name' => $row['image_name'],
        ':image_mime' => $row['image_mime'],
        ':image_size' => $row['image_size'],
        ':is_pinned' => $row['is_pinned'],
        ':pinned_by_user_id' => $row['pinned_by_user_id'],
        ':pinned_at' => $row['pinned_at'],
        ':is_resolved' => $row['is_resolved'],
        ':resolved_by_user_id' => $row['resolved_by_user_id'],
        ':resolved_at' => $row['resolved_at'],
        ':edited_at' => $row['edited_at'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_feed_like(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO feed_post_likes (post_id, user_id, created_at)
         VALUES (:post_id, :user_id, :created_at)'
    );
    $stmt->execute([
        ':post_id' => $row['post_id'],
        ':user_id' => $row['user_id'],
        ':created_at' => $row['created_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_feed_save(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO feed_post_saves (post_id, user_id, created_at)
         VALUES (:post_id, :user_id, :created_at)'
    );
    $stmt->execute([
        ':post_id' => $row['post_id'],
        ':user_id' => $row['user_id'],
        ':created_at' => $row['created_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_quiz(PDO $pdo, array $row): int
{
    $sql = 'INSERT INTO quizzes
        (subject_id, created_by_user_id, title, description, duration_minutes, mode, status, rejection_reason, reviewed_by_user_id, reviewed_at, created_at, updated_at)
        VALUES
        (:subject_id, :created_by_user_id, :title, :description, :duration_minutes, :mode, :status, :rejection_reason, :reviewed_by_user_id, :reviewed_at, :created_at, :updated_at)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':subject_id' => $row['subject_id'],
        ':created_by_user_id' => $row['created_by_user_id'],
        ':title' => $row['title'],
        ':description' => $row['description'],
        ':duration_minutes' => $row['duration_minutes'],
        ':mode' => $row['mode'],
        ':status' => $row['status'],
        ':rejection_reason' => $row['rejection_reason'],
        ':reviewed_by_user_id' => $row['reviewed_by_user_id'],
        ':reviewed_at' => $row['reviewed_at'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_quiz_questions_with_options(PDO $pdo, int $quizId, string $subjectName, int $questionCount): array
{
    $rows = [];
    for ($q = 1; $q <= $questionCount; $q++) {
        $questionText = sprintf(
            'Q%d. Which statement best explains a core concept of %s?',
            $q,
            $subjectName
        );

        $questionId = seed_insert_quiz_question($pdo, [
            'quiz_id' => $quizId,
            'question_text' => $questionText,
            'sort_order' => $q,
            'created_at' => seed_random_datetime('-120 days', '-1 days'),
            'updated_at' => seed_random_datetime('-20 days', '-1 days'),
        ]);

        $options = [];
        for ($o = 1; $o <= 4; $o++) {
            $isCorrect = $o === 1 ? 1 : 0;
            $optionId = seed_insert_quiz_option($pdo, [
                'question_id' => $questionId,
                'option_text' => seed_quiz_option_text($subjectName, $q, $o, $isCorrect === 1),
                'is_correct' => $isCorrect,
                'sort_order' => $o,
                'created_at' => seed_random_datetime('-120 days', '-1 days'),
                'updated_at' => seed_random_datetime('-20 days', '-1 days'),
            ]);

            $options[] = [
                'id' => $optionId,
                'is_correct' => $isCorrect,
            ];
        }

        $rows[] = [
            'id' => $questionId,
            'sort_order' => $q,
            'options' => $options,
        ];
    }

    return $rows;
}

function seed_quiz_option_text(string $subjectName, int $questionNumber, int $optionNumber, bool $correct): string
{
    if ($correct) {
        return 'Correct concept application for ' . $subjectName . ' in question ' . $questionNumber . '.';
    }

    return 'Distractor option ' . $optionNumber . ' for ' . $subjectName . ' question ' . $questionNumber . '.';
}

function seed_insert_quiz_question(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO quiz_questions (quiz_id, question_text, sort_order, created_at, updated_at)
         VALUES (:quiz_id, :question_text, :sort_order, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':quiz_id' => $row['quiz_id'],
        ':question_text' => $row['question_text'],
        ':sort_order' => $row['sort_order'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_quiz_option(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO quiz_options (question_id, option_text, is_correct, sort_order, created_at, updated_at)
         VALUES (:question_id, :option_text, :is_correct, :sort_order, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':question_id' => $row['question_id'],
        ':option_text' => $row['option_text'],
        ':is_correct' => $row['is_correct'],
        ':sort_order' => $row['sort_order'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_quiz_attempt(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO quiz_attempts
         (quiz_id, user_id, status, started_at, expires_at, submitted_at, correct_count, total_questions, score_percent, created_at, updated_at)
         VALUES
         (:quiz_id, :user_id, :status, :started_at, :expires_at, :submitted_at, :correct_count, :total_questions, :score_percent, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':quiz_id' => $row['quiz_id'],
        ':user_id' => $row['user_id'],
        ':status' => $row['status'],
        ':started_at' => $row['started_at'],
        ':expires_at' => $row['expires_at'],
        ':submitted_at' => $row['submitted_at'],
        ':correct_count' => $row['correct_count'],
        ':total_questions' => $row['total_questions'],
        ':score_percent' => $row['score_percent'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_quiz_attempt_answer(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO quiz_attempt_answers
         (attempt_id, question_id, selected_option_id, is_correct, created_at, updated_at)
         VALUES
         (:attempt_id, :question_id, :selected_option_id, :is_correct, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':attempt_id' => $row['attempt_id'],
        ':question_id' => $row['question_id'],
        ':selected_option_id' => $row['selected_option_id'],
        ':is_correct' => $row['is_correct'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_kuppi_timetable_slot(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO kuppi_university_timetable_slots
         (batch_id, day_of_week, start_time, end_time, reason, created_by_user_id, updated_by_user_id, created_at, updated_at)
         VALUES
         (:batch_id, :day_of_week, :start_time, :end_time, :reason, :created_by_user_id, :updated_by_user_id, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':batch_id' => $row['batch_id'],
        ':day_of_week' => $row['day_of_week'],
        ':start_time' => $row['start_time'],
        ':end_time' => $row['end_time'],
        ':reason' => $row['reason'],
        ':created_by_user_id' => $row['created_by_user_id'],
        ':updated_by_user_id' => $row['updated_by_user_id'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_kuppi_request(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO kuppi_requests
         (batch_id, subject_id, requested_by_user_id, title, description, tags_csv, status, created_at, updated_at)
         VALUES
         (:batch_id, :subject_id, :requested_by_user_id, :title, :description, :tags_csv, :status, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':batch_id' => $row['batch_id'],
        ':subject_id' => $row['subject_id'],
        ':requested_by_user_id' => $row['requested_by_user_id'],
        ':title' => $row['title'],
        ':description' => $row['description'],
        ':tags_csv' => $row['tags_csv'],
        ':status' => $row['status'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_kuppi_request_vote(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO kuppi_request_votes
         (request_id, user_id, vote_type, created_at, updated_at)
         VALUES
         (:request_id, :user_id, :vote_type, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':request_id' => $row['request_id'],
        ':user_id' => $row['user_id'],
        ':vote_type' => $row['vote_type'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_kuppi_conductor_application(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO kuppi_conductor_applications
         (request_id, applicant_user_id, motivation, availability_csv, created_at, updated_at)
         VALUES
         (:request_id, :applicant_user_id, :motivation, :availability_csv, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':request_id' => $row['request_id'],
        ':applicant_user_id' => $row['applicant_user_id'],
        ':motivation' => $row['motivation'],
        ':availability_csv' => $row['availability_csv'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    $id = (int) $pdo->lastInsertId();
    if ($id > 0) {
        return $id;
    }

    return (int) seed_fetch_value(
        $pdo,
        'SELECT id FROM kuppi_conductor_applications WHERE request_id = ? AND applicant_user_id = ? LIMIT 1',
        [$row['request_id'], $row['applicant_user_id']]
    );
}

function seed_insert_kuppi_conductor_vote(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO kuppi_conductor_votes
         (application_id, voter_user_id, created_at, updated_at)
         VALUES
         (:application_id, :voter_user_id, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':application_id' => $row['application_id'],
        ':voter_user_id' => $row['voter_user_id'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_kuppi_scheduled_session(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO kuppi_scheduled_sessions
         (batch_id, subject_id, request_id, title, description, session_date, start_time, end_time, duration_minutes, max_attendees, location_type, location_text, meeting_link, notes, status, created_by_user_id, cancelled_by_user_id, cancelled_at, created_at, updated_at)
         VALUES
         (:batch_id, :subject_id, :request_id, :title, :description, :session_date, :start_time, :end_time, :duration_minutes, :max_attendees, :location_type, :location_text, :meeting_link, :notes, :status, :created_by_user_id, :cancelled_by_user_id, :cancelled_at, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':batch_id' => $row['batch_id'],
        ':subject_id' => $row['subject_id'],
        ':request_id' => $row['request_id'],
        ':title' => $row['title'],
        ':description' => $row['description'],
        ':session_date' => $row['session_date'],
        ':start_time' => $row['start_time'],
        ':end_time' => $row['end_time'],
        ':duration_minutes' => $row['duration_minutes'],
        ':max_attendees' => $row['max_attendees'],
        ':location_type' => $row['location_type'],
        ':location_text' => $row['location_text'],
        ':meeting_link' => $row['meeting_link'],
        ':notes' => $row['notes'],
        ':status' => $row['status'],
        ':created_by_user_id' => $row['created_by_user_id'],
        ':cancelled_by_user_id' => $row['cancelled_by_user_id'],
        ':cancelled_at' => $row['cancelled_at'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_kuppi_scheduled_session_host(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO kuppi_scheduled_session_hosts
         (session_id, host_user_id, source_type, source_application_id, assigned_by_user_id, created_at, updated_at)
         VALUES
         (:session_id, :host_user_id, :source_type, :source_application_id, :assigned_by_user_id, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':session_id' => $row['session_id'],
        ':host_user_id' => $row['host_user_id'],
        ':source_type' => $row['source_type'],
        ':source_application_id' => $row['source_application_id'],
        ':assigned_by_user_id' => $row['assigned_by_user_id'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_gpa_grade_scale(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO gpa_batch_grade_scales
         (batch_id, letter_grade, description, grade_point, sort_order, created_by_user_id, updated_by_user_id, created_at, updated_at)
         VALUES
         (:batch_id, :letter_grade, :description, :grade_point, :sort_order, :created_by_user_id, :updated_by_user_id, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':batch_id' => $row['batch_id'],
        ':letter_grade' => $row['letter_grade'],
        ':description' => $row['description'],
        ':grade_point' => $row['grade_point'],
        ':sort_order' => $row['sort_order'],
        ':created_by_user_id' => $row['created_by_user_id'],
        ':updated_by_user_id' => $row['updated_by_user_id'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_gpa_term_record(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO gpa_term_records
         (user_id, batch_id, academic_year, semester, semester_gpa, total_credits, graded_subject_count, created_at, updated_at)
         VALUES
         (:user_id, :batch_id, :academic_year, :semester, :semester_gpa, :total_credits, :graded_subject_count, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':user_id' => $row['user_id'],
        ':batch_id' => $row['batch_id'],
        ':academic_year' => $row['academic_year'],
        ':semester' => $row['semester'],
        ':semester_gpa' => $row['semester_gpa'],
        ':total_credits' => $row['total_credits'],
        ':graded_subject_count' => $row['graded_subject_count'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_gpa_term_subject_entry(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO gpa_term_subject_entries
         (term_record_id, subject_id, subject_name_snapshot, credit_value, letter_grade, grade_point_snapshot, quality_points, created_at, updated_at)
         VALUES
         (:term_record_id, :subject_id, :subject_name_snapshot, :credit_value, :letter_grade, :grade_point_snapshot, :quality_points, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':term_record_id' => $row['term_record_id'],
        ':subject_id' => $row['subject_id'],
        ':subject_name_snapshot' => $row['subject_name_snapshot'],
        ':credit_value' => $row['credit_value'],
        ':letter_grade' => $row['letter_grade'],
        ':grade_point_snapshot' => $row['grade_point_snapshot'],
        ':quality_points' => $row['quality_points'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_comment(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO comments
         (target_type, target_id, parent_comment_id, depth, user_id, body, created_at, updated_at)
         VALUES
         (:target_type, :target_id, :parent_comment_id, :depth, :user_id, :body, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':target_type' => $row['target_type'],
        ':target_id' => $row['target_id'],
        ':parent_comment_id' => $row['parent_comment_id'],
        ':depth' => $row['depth'],
        ':user_id' => $row['user_id'],
        ':body' => $row['body'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_insert_feed_report(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO feed_reports
         (batch_id, target_type, target_id, reporter_user_id, reason, details, status, reviewed_by_user_id, reviewed_at, action_taken, created_at, updated_at)
         VALUES
         (:batch_id, :target_type, :target_id, :reporter_user_id, :reason, :details, :status, :reviewed_by_user_id, :reviewed_at, :action_taken, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':batch_id' => $row['batch_id'],
        ':target_type' => $row['target_type'],
        ':target_id' => $row['target_id'],
        ':reporter_user_id' => $row['reporter_user_id'],
        ':reason' => $row['reason'],
        ':details' => $row['details'],
        ':status' => $row['status'],
        ':reviewed_by_user_id' => $row['reviewed_by_user_id'],
        ':reviewed_at' => $row['reviewed_at'],
        ':action_taken' => $row['action_taken'],
        ':created_at' => $row['created_at'],
        ':updated_at' => $row['updated_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function seed_find_subject_by_id(array $subjects, int $id): ?array
{
    foreach ($subjects as $subject) {
        if ((int) $subject['id'] === $id) {
            return $subject;
        }
    }

    return null;
}

function seed_fetch_value(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();

    return $value === false ? null : $value;
}

function seed_random_datetime(string $from, string $to): string
{
    $start = strtotime($from);
    $end = strtotime($to);

    if ($start === false || $end === false) {
        throw new RuntimeException('Invalid random datetime range: ' . $from . ' -> ' . $to);
    }

    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }

    $ts = mt_rand($start, $end);
    return date('Y-m-d H:i:s', $ts);
}

function seed_random_date(string $from, string $to): string
{
    $dateTime = seed_random_datetime($from, $to);
    return substr($dateTime, 0, 10);
}

function seed_datetime_add_minutes(string $dateTime, int $minutes): string
{
    $ts = strtotime($dateTime);
    if ($ts === false) {
        throw new RuntimeException('Invalid datetime for add minutes: ' . $dateTime);
    }

    return date('Y-m-d H:i:s', $ts + ($minutes * 60));
}

function seed_random_slug(int $length = 8): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
    $max = strlen($chars) - 1;
    $slug = '';
    for ($i = 0; $i < $length; $i++) {
        $slug .= $chars[mt_rand(0, $max)];
    }

    return $slug;
}

function seed_delete_directory_recursive(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $itemPath = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath)) {
            seed_delete_directory_recursive($itemPath);
            continue;
        }

        @unlink($itemPath);
    }

    @rmdir($path);
}

function seed_log(string $message): void
{
    fwrite(STDOUT, '[seed:ucsc-is21] ' . $message . PHP_EOL);
}

function seed_log_progress(string $label, int $current, int $total, int $every = 10): void
{
    $safeTotal = max(1, $total);
    $safeEvery = max(1, $every);
    if ($current <= 1 || $current >= $safeTotal || $current % $safeEvery === 0) {
        $percent = (int) round(($current / $safeTotal) * 100);
        seed_log(sprintf('%s: %d/%d (%d%%)', $label, $current, $safeTotal, $percent));
    }
}

function seed_string_contains_any(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        $needleText = strtolower(trim((string) $needle));
        if ($needleText === '') {
            continue;
        }
        if (str_contains($haystack, $needleText)) {
            return true;
        }
    }

    return false;
}
