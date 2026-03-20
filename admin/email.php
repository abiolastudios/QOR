<?php
require_once 'includes/auth.php';
require_once 'includes/layout.php';

requireRole('super_admin');

renderHeader('Email System', 'email');
?>

<div class="dashboard-grid">
    <!-- SMTP Config -->
    <div class="card">
        <div class="card-header"><h2>SMTP Configuration</h2></div>
        <div class="card-body">
            <div class="info-list">
                <div class="info-item">
                    <span class="info-label">Host</span>
                    <span class="info-value"><code><?= SMTP_HOST ?></code></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Port</span>
                    <span class="info-value"><code><?= SMTP_PORT ?></code></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Security</span>
                    <span class="info-value"><code><?= SMTP_SECURE ?></code></span>
                </div>
                <div class="info-item">
                    <span class="info-label">From Email</span>
                    <span class="info-value"><code><?= SMTP_FROM_EMAIL ?></code></span>
                </div>
                <div class="info-item">
                    <span class="info-label">From Name</span>
                    <span class="info-value"><?= SMTP_FROM_NAME ?></span>
                </div>
                <div class="info-item" style="border:none">
                    <span class="info-label">Password</span>
                    <span class="info-value"><?= SMTP_PASS ? '<span class="badge-green">Set</span>' : '<span class="badge-red">Not Set</span>' ?></span>
                </div>
            </div>
            <p style="font-size:0.75rem;color:var(--text-muted);margin-top:12px;">Edit <code>admin/includes/config.php</code> to update SMTP settings.</p>
        </div>
    </div>

    <!-- Test Email -->
    <div class="card">
        <div class="card-header"><h2>Test SMTP</h2></div>
        <div class="card-body">
            <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:16px;">Send a test email to verify your SMTP configuration is working.</p>
            <form method="POST" action="api/email.php?action=test_smtp">
                <?= csrfField() ?>
                <div class="form-group">
                    <label>Send Test To</label>
                    <input type="email" name="test_email" placeholder="your@email.com" required>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:12px">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/></svg>
                    Send Test Email
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Email Templates -->
<div class="card">
    <div class="card-header"><h2>Automated Email Templates</h2></div>
    <div class="card-body">
        <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:24px;">These emails are sent automatically when triggered by user actions.</p>

        <div class="email-templates-grid">
            <div class="email-tpl-card">
                <div class="email-tpl-header">
                    <span class="email-tpl-trigger">Waitlist Signup</span>
                    <span class="badge-green">Active</span>
                </div>
                <h4>Welcome to Core Chain</h4>
                <p>Sent when someone joins the waitlist. Includes early access info and whitepaper link.</p>
            </div>

            <div class="email-tpl-card">
                <div class="email-tpl-header">
                    <span class="email-tpl-trigger">Contact Form</span>
                    <span class="badge-green">Active</span>
                </div>
                <h4>Message Received</h4>
                <p>Auto-reply confirming receipt. Includes FAQ link and 48-hour response promise.</p>
            </div>

            <div class="email-tpl-card">
                <div class="email-tpl-header">
                    <span class="email-tpl-trigger">Admin Reply</span>
                    <span class="badge-green">Active</span>
                </div>
                <h4>Reply from Core Chain</h4>
                <p>Sent when admin replies to a contact message. Includes the reply text.</p>
            </div>

            <div class="email-tpl-card">
                <div class="email-tpl-header">
                    <span class="email-tpl-trigger">Newsletter Subscribe</span>
                    <span class="badge-green">Active</span>
                </div>
                <h4>You're Subscribed</h4>
                <p>Welcome email for new newsletter subscribers. Includes unsubscribe link.</p>
            </div>

            <div class="email-tpl-card">
                <div class="email-tpl-header">
                    <span class="email-tpl-trigger">Campaign Send</span>
                    <span class="badge-green">Active</span>
                </div>
                <h4>Newsletter Campaign</h4>
                <p>Bulk email to all active subscribers. Personalized unsubscribe links per recipient.</p>
            </div>
        </div>
    </div>
</div>

<!-- Automation Flows -->
<div class="card">
    <div class="card-header"><h2>Automation Flows</h2></div>
    <div class="card-body">
        <div class="flow-list">
            <div class="flow-item">
                <div class="flow-trigger">Waitlist Signup</div>
                <div class="flow-arrow">&rarr;</div>
                <div class="flow-action">Welcome Email</div>
                <div class="flow-arrow">&rarr;</div>
                <div class="flow-action flow-future">Follow-up (3 days) <span class="badge-gray">Coming</span></div>
            </div>
            <div class="flow-item">
                <div class="flow-trigger">Contact Form</div>
                <div class="flow-arrow">&rarr;</div>
                <div class="flow-action">Auto-Reply</div>
                <div class="flow-arrow">&rarr;</div>
                <div class="flow-action">Admin Notification</div>
            </div>
            <div class="flow-item">
                <div class="flow-trigger">Admin Replies</div>
                <div class="flow-arrow">&rarr;</div>
                <div class="flow-action">Reply Email to User</div>
            </div>
            <div class="flow-item">
                <div class="flow-trigger">Newsletter Subscribe</div>
                <div class="flow-arrow">&rarr;</div>
                <div class="flow-action">Welcome Email</div>
            </div>
            <div class="flow-item">
                <div class="flow-trigger">New Blog Post</div>
                <div class="flow-arrow">&rarr;</div>
                <div class="flow-action flow-future">Auto-notify Subscribers <span class="badge-gray">Coming</span></div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
