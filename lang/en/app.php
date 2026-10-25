<?php

/*
 * Every string the interface shows, shared with React by
 * HandleInertiaRequests and read with useTranslations().t('…').
 * The Arabic twin is lang/ar/app.php; both keep the same keys.
 */

return [

    'brand' => [
        'name' => 'Mirsalink',
        'tagline' => 'Your team’s WhatsApp conversations, in one inbox.',
    ],

    'common' => [
        'save' => 'Save',
        'saved' => 'Saved.',
        'cancel' => 'Cancel',
        'continue' => 'Continue',
        'back' => 'Back',
        'confirm' => 'Confirm',
        'close' => 'Close',
        'soon' => 'Soon',
        'show_password' => 'Show password',
        'hide_password' => 'Hide password',
        'copy' => 'Copy',
        'copied' => 'Copied',
    ],

    'locale' => [
        'label' => 'Language',
        'ar' => 'العربية',
        'en' => 'English',
    ],

    'appearance' => [
        'label' => 'Appearance',
        'light' => 'Light',
        'dark' => 'Dark',
        'system' => 'Device',
    ],

    'nav' => [
        'workspace' => 'Daily work',
        'management' => 'Management',
        'dashboard' => 'Home',
        'inbox' => 'Inbox',
        'contacts' => 'Contacts',
        'broadcasts' => 'Broadcasts',
        'templates' => 'Templates and replies',
        'knowledge' => 'Knowledge base',
        'reports' => 'Reports',
        'team' => 'Team',
        'routing' => 'Tags and routing',
        'toggle_sidebar' => 'Show or hide the menu',
        'breadcrumbs' => 'Page path',
    ],

    'user_menu' => [
        'settings' => 'Settings',
        'logout' => 'Log out',
    ],

    'auth' => [
        'panel' => [
            'headline' => 'Every conversation reaches the person who answers it.',
            'body' => 'The assistant understands what each customer asks, tags it, and hands it to the right person at the right time.',
            'scene' => [
                'customer' => 'We have 40 employees. Can I talk to someone in sales?',
                'handover' => 'The assistant handed this to Sara',
                'agent' => 'Hi, this is Sara from sales.',
            ],
        ],
        'login' => [
            'title' => 'Log in',
            'heading' => 'Welcome back',
            'description' => 'Log in with your email and password.',
            'email' => 'Email',
            'password' => 'Password',
            'forgot' => 'Forgot your password?',
            'remember' => 'Remember me on this device',
            'submit' => 'Log in',
            'no_account' => 'No account? Your team’s supervisor adds you.',
        ],
        'passkey' => [
            'sign_in' => 'Log in with a passkey',
            'authenticating' => 'Checking…',
            'or_email' => 'Or with email',
            'confirm' => 'Confirm with a passkey',
            'confirming' => 'Confirming…',
            'or_password' => 'Or with your password',
        ],
        'forgot' => [
            'title' => 'Forgot password',
            'description' => 'Enter your email and we’ll send you a link to set a new password.',
            'submit' => 'Send the link',
            'back' => 'Back to log in',
        ],
        'reset' => [
            'title' => 'New password',
            'description' => 'Choose a new password for your account.',
            'password' => 'New password',
            'confirm' => 'Confirm password',
            'submit' => 'Save password',
        ],
        'confirm' => [
            'title' => 'Confirm your password',
            'description' => 'This is a protected area. Confirm your password to continue.',
            'submit' => 'Confirm',
        ],
        'verify' => [
            'title' => 'Confirm your email',
            'description' => 'We emailed you a confirmation link. Open it to continue.',
            'sent' => 'We sent a new confirmation link to your email.',
            'resend' => 'Send the link again',
            'logout' => 'Log out',
        ],
        'two_factor' => [
            'title' => 'Two-step verification',
            'code_title' => 'Verification code',
            'code_description' => 'Enter the code shown in the authenticator app on your phone.',
            'code_toggle' => 'Use a recovery code instead',
            'recovery_title' => 'Recovery code',
            'recovery_description' => 'Enter one of the recovery codes you saved when you turned on two-step verification.',
            'recovery_toggle' => 'Use your authenticator code',
            'recovery_placeholder' => 'Recovery code',
            'submit' => 'Continue',
        ],
    ],

    'settings' => [
        'title' => 'Settings',
        'description' => 'Your profile, your account’s security and how the platform looks.',
        'nav' => [
            'profile' => 'Profile',
            'security' => 'Security',
            'appearance' => 'Appearance',
        ],
        'profile' => [
            'heading' => 'Profile',
            'description' => 'Your name as the team sees it, and the email you log in with.',
            'name' => 'Name',
            'name_placeholder' => 'Full name',
            'email' => 'Email',
            'unverified' => 'Your email isn’t confirmed yet.',
            'resend' => 'Send the confirmation link again.',
            'link_sent' => 'We sent a new confirmation link to your email.',
        ],
        'delete' => [
            'heading' => 'Delete account',
            'description' => 'Deletes your account and everything in it for good.',
            'warning' => 'This can’t be undone.',
            'button' => 'Delete account',
            'dialog_title' => 'Delete your account for good?',
            'dialog_body' => 'Your account and all its data are deleted and can’t be recovered. Enter your password to confirm.',
        ],
        'security' => [
            'heading' => 'Change password',
            'description' => 'Use a long password you don’t use anywhere else.',
            'current' => 'Current password',
            'new' => 'New password',
            'confirm' => 'Confirm password',
        ],
        'two_factor' => [
            'heading' => 'Two-step verification',
            'description' => 'A code from your phone, asked for with your password when you log in.',
            'enabled_body' => 'Two-step verification is on. When you log in, we ask for a code from the authenticator app on your phone.',
            'disabled_body' => 'Once it’s on, we ask for a code from an authenticator app on your phone when you log in, such as Google Authenticator or Microsoft Authenticator.',
            'enable' => 'Turn on two-step verification',
            'disable' => 'Turn off two-step verification',
            'continue_setup' => 'Continue setup',
        ],
        'two_factor_setup' => [
            'enabled_title' => 'Two-step verification is on',
            'enabled_description' => 'Scan the code or enter the key in your authenticator app.',
            'verify_title' => 'Confirm the code',
            'verify_description' => 'Enter the 6-digit code from your authenticator app.',
            'enable_title' => 'Turn on two-step verification',
            'enable_description' => 'Scan the code with the authenticator app on your phone, or enter the key by hand.',
            'manual' => 'or enter the key by hand',
        ],
        'recovery' => [
            'title' => 'Recovery codes',
            'description' => 'They get you back into your account if you lose your phone. Keep them in a secure password manager.',
            'show' => 'Show recovery codes',
            'hide' => 'Hide recovery codes',
            'regenerate' => 'New codes',
            'note' => 'Each code works once and is then cancelled. If you need more, press “New codes”.',
            'list' => 'Recovery codes',
            'loading' => 'Loading recovery codes',
        ],
        'passkeys' => [
            'heading' => 'Passkeys',
            'description' => 'Log in with your fingerprint or your device’s lock, without a password.',
            'empty_title' => 'No passkeys yet',
            'empty_body' => 'Add a passkey to log in without your password.',
            'added' => 'Added :time',
            'last_used' => 'Last used :time',
            'remove' => 'Remove',
            'remove_title' => 'Remove passkey',
            'remove_body' => 'Remove the “:name” passkey? You won’t be able to log in with it any more.',
            'removing' => 'Removing…',
            'remove_confirm' => 'Remove passkey',
            'unsupported' => 'This browser doesn’t support passkeys.',
            'add' => 'Add a passkey',
            'name' => 'Passkey name',
            'name_placeholder' => 'e.g. Work laptop, My phone',
            'name_hint' => 'A name helps you tell this passkey apart later.',
            'default_name' => ':browser on :os',
            'registering' => 'Adding…',
            'register' => 'Add passkey',
        ],
        'appearance' => [
            'heading' => 'Appearance',
            'description' => 'Light, dark, or whatever your device uses.',
        ],
    ],

    'dashboard' => [
        'title' => 'Home',
        'greeting' => 'Hello, :name',
        'subtitle' => 'Your day at a glance once the inbox is running, and what’s left to set the platform up.',
        'stats' => [
            'open' => 'Open conversations',
            'waiting' => 'Waiting for a person',
            'first_response' => 'Average first reply',
            'closed_today' => 'Closed today',
            'empty' => 'The figures start once the inbox is running.',
        ],
        'setup' => [
            'title' => 'Start here',
            'description' => 'Five steps to set the platform up before its first customer.',
            'done' => 'Done',
            'steps' => [
                'account' => [
                    'title' => 'Supervisor account',
                    'body' => 'You’re logged in, and can turn on two-step verification in Settings.',
                ],
                'team' => [
                    'title' => 'Add your team',
                    'body' => 'Team members and supervisors, working hours, and who is available.',
                ],
                'routing' => [
                    'title' => 'Tags and routing rules',
                    'body' => 'How conversations are tagged, and who takes each tag.',
                ],
                'contacts' => [
                    'title' => 'Import contacts',
                    'body' => 'From a CSV file, including what you export from Respond.io.',
                ],
                'whatsapp' => [
                    'title' => 'Connect a WhatsApp number',
                    'body' => 'The last step, once everything works on the simulator.',
                ],
            ],
        ],
    ],

];
