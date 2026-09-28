<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$portal = new Portal(Database::getInstance());
$unreadNotifications = $portal->getStudentUnreadNotificationCount((int)($user['id'] ?? 0));
$notifications = $portal->getStudentNotifications((int)($user['id'] ?? 0));
?>
<div class="student-page-shell container py-5">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require_once dirname(__DIR__) . '/templates/student-sidebar.php'; ?>
        </div>
        <div class="col-lg-9">
            <div class="student-community-page">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">Community Hub</h4>
                        <p class="text-secondary mb-0">Share updates, ask questions, and follow discussions with your peers.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="communityRefreshBtn"><i class="fa-solid fa-arrow-rotate-right"></i> Refresh</button>
                        <div class="notification-pill"><i class="fa-solid fa-bell"></i> <?= e($unreadNotifications) ?></div>
                    </div>
                </div>

                <div class="community-chat-shell glass-card">
                    <div class="community-chat-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center p-4 border-bottom">
                        <div>
                            <h5 class="fw-bold mb-1">Community Chat</h5>
                            <p class="text-secondary mb-1">Send messages to the NACOS community in a modern chat experience.</p>
                            <div class="small text-secondary">Public community username: <span id="communityUsernameDisplay" class="text-success fw-semibold">@YourName</span></div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 mt-md-0">
                            <button type="button" class="btn btn-outline-success btn-sm" id="communityChangeUsernameBtn">Change username</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="communityRefreshIconBtn"><i data-lucide="refresh-cw"></i></button>
                            <div class="notification-pill mb-0"><i class="fa-solid fa-bell"></i> <?= e($unreadNotifications) ?></div>
                        </div>
                    </div>

                    <div class="community-chat-body">
                        <div id="communityReplyPreview" class="community-reply-preview d-none"></div>
                        <div id="communityConversation" class="community-conversation px-4 py-4" data-current-student-id="<?= e($user['id'] ?? 0) ?>" data-csrf-token="<?= e(csrf_token()) ?>">
                            <div id="communityEmpty" class="community-empty text-center mx-auto py-5 px-3">
                                <p class="mb-2 fw-semibold">Your community chat is ready.</p>
                                <p class="text-secondary mb-0">Type a message, attach a file, and participate in the NACOS community.</p>
                            </div>
                        </div>
                    </div>

                    <div class="community-input-bar p-4 border-top position-relative">
                        <div class="community-attachment-menu d-none" id="communityAttachmentMenu">
                            <button type="button" class="attachment-option" data-accept="image/*" data-type="image"><i data-lucide="image"></i> Image</button>
                            <button type="button" class="attachment-option" data-accept="*/*" data-type="file"><i data-lucide="paperclip"></i> Attachment</button>
                            <button type="button" class="attachment-option" data-accept=".doc,.docx" data-type="doc"><i data-lucide="file-text"></i> Document</button>
                            <button type="button" class="attachment-option" data-accept=".pdf" data-type="pdf"><i data-lucide="file-pdf"></i> PDF</button>
                        </div>
                        <div class="d-flex gap-2 align-items-end flex-column flex-sm-row">
                            <button type="button" class="btn btn-soft-surface community-attachment-btn" id="communityAttachmentBtn"><i data-lucide="plus"></i></button>
                            <div class="flex-grow-1">
                                <label for="communityPostInput" class="visually-hidden">Community message</label><textarea id="communityPostInput" class="form-control chat-input" rows="2"></textarea>
                                <div id="communityAttachmentPreview" class="attachment-preview d-none mt-3"></div>
                            </div>
                            <button type="button" class="btn btn-success community-send-btn" id="communitySendBtn"><i data-lucide="send"></i></button>
                        </div>
                        <input type="file" id="communityAttachmentInput" class="d-none">
                    </div>
                </div>

                <div id="communityUsernameModal" class="community-username-modal d-none">
                    <div class="community-username-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="fw-bold mb-1">Choose your Community username</h5>
                                <p class="text-secondary mb-0">This is the name other students will see in the community chat.</p>
                            </div>
                            <button type="button" class="btn btn-sm btn-soft-surface" id="communityUsernameClose"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <form id="communityUsernameForm" class="mb-3">
                            <label class="form-label fw-semibold" for="communityUsernameField">@ Username</label>
                            <label for="communityUsernameField" class="visually-hidden">Preferred username</label><input type="text" id="communityUsernameField" class="form-control" maxlength="20" autocomplete="off">
                            <div id="communityUsernameError" class="form-text text-danger d-none"></div>
                            <div class="form-text text-secondary mt-2">Use letters, numbers, and underscores; 3–20 characters.</div>
                        </form>
                        <button type="button" class="btn btn-success w-100" id="communityUsernameSave">Save username</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
