<?php
session_start();
require_once '../includes/database.php';

if(!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true){
    header("location: ../auth/login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$adminName = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Chats - iShoeKicks Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-gold: #B58E53;
            --primary-gradient: linear-gradient(135deg, #B58E53 0%, #D4A574 100%);
            --bg-dark: #2b2b2b;
            --panel-bg: #3a3a3a;
            --border-color: #4a4a4a;
            --text-primary: #ffffff;
            --text-secondary: #d4d4d4;
            --user-bubble: #4a4a4a;
            --admin-bubble: #B58E53;
            --hover-bg: #454545;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-dark);
            min-height: 100vh;
            color: var(--text-primary);
        }

        main {
            margin-left: 250px;
            margin-top: 50px;
            padding: 35px 40px;
        }

        .archive-wrapper {
            background: var(--panel-bg);
            border: 1px solid rgba(181, 142, 83, 0.2);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            min-height: calc(100vh - 140px);
        }

        .archive-header {
            background: linear-gradient(135deg, #3a3a3a 0%, #4a4a4a 100%);
            padding: 20px 24px;
            border-bottom: 1px solid var(--primary-gold);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .archive-header h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--primary-gold);
        }

        .back-button {
            background: transparent;
            border: 1px solid var(--primary-gold);
            color: var(--primary-gold);
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .back-button:hover {
            background: var(--primary-gold);
            color: #000;
        }

        .archive-content {
            padding: 24px;
        }

        .archive-list {
            display: grid;
            gap: 16px;
            /* Responsive Grid */
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        }

        .archive-card {
            background: #353535;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
        }

        .archive-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            border-color: var(--primary-gold);
            transform: translateY(-2px);
        }

        .archive-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .archive-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .archive-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-weight: 600;
            font-size: 18px;
            border: 2px solid #4a4a4a;
        }

        .archive-user-details h3 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            color: #fff;
        }

        .archive-user-details p {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 0;
        }

        .archive-meta {
            display: flex;
            gap: 16px;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .archive-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .archive-meta i {
            color: var(--primary-gold);
        }

        .archive-actions {
            display: flex;
            gap: 10px;
        }

        .btn-view {
            flex: 1;
            padding: 8px 16px;
            background: var(--primary-gradient);
            color: #000;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-view:hover {
            opacity: 0.9;
        }

        .btn-delete {
            padding: 8px 16px;
            background: transparent;
            color: #dc3545;
            border: 1px solid #dc3545;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-delete:hover {
            background: #dc3545;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            grid-column: 1 / -1; 
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: var(--primary-gold);
            opacity: 0.5;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px);
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: var(--panel-bg);
            border: 1px solid var(--primary-gold);
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }

        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #3a3a3a 0%, #4a4a4a 100%);
            border-radius: 12px 12px 0 0;
        }

        .modal-header h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: var(--primary-gold);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.2s;
        }
        
        .close-modal:hover {
            color: #fff;
        }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: var(--bg-dark);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        /* Message Styles (Reused from live chat) */
        .message-group {
            display: flex;
            margin-bottom: 5px;
        }
        .message-group.user { justify-content: flex-start; }
        .message-group.admin { justify-content: flex-end; }
        
        .message-bubble {
            padding: 10px 16px;
            border-radius: 18px;
            max-width: 80%;
            word-wrap: break-word;
        }
        
        .message-group.user .message-bubble {
            background: var(--user-bubble);
            color: #fff;
            border-bottom-left-radius: 4px;
        }
        
        .message-group.admin .message-bubble {
            background: var(--primary-gradient);
            color: #000;
            border-bottom-right-radius: 4px;
            font-weight: 500;
        }
        
        .message-time {
            font-size: 11px;
            margin-top: 4px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        .message-group.admin .message-time { text-align: right; }

        @media (max-width: 768px) {
            main { margin-left: 0; padding: 15px; }
            .archive-list { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <main>
        <div class="archive-wrapper">
            <div class="archive-header">
                <h1><i class="fas fa-archive"></i> Archived Chats</h1>
                <a href="livechat.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Live Chat
                </a>
            </div>

            <div class="archive-content">
                <div id="archiveList" class="archive-list">
                    <div class="empty-state">
                        <div class="spinner-border text-warning" role="status"></div>
                        <p class="mt-3">Loading archived chats...</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="messageModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalUserName">Chat History</h2>
                <button class="close-modal" onclick="closeModal()">×</button>
            </div>
            <div id="modalMessages" class="modal-body">
                </div>
        </div>
    </div>

    <script>
    const ADMIN_NAME = <?php echo json_encode($adminName); ?>;
    const ARCHIVE_API_URL = 'http://localhost/ishoekicksadmin/admin/pages/api/archive_chat.php';

    function getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    }

    function formatTime(timestamp) {
        const date = new Date(timestamp);
        if (isNaN(date.getTime())) return '';
        
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    async function loadArchivedChats() {
        try {
            // Updated API endpoint to point to archive_chat.php with action=list_archived
            // Assuming your archive_chat.php handles listing via POST or GET 'list' action
            // Or use a separate api file like get_archived_list.php if you prefer.
            // Here I will use the same endpoint structure as your previous code:
            const response = await fetch('api/get_archived_list.php'); 
            const data = await response.json();
            const archiveList = document.getElementById('archiveList');
            
            if (data.status === 'success' && data.archives.length > 0) {
                archiveList.innerHTML = '';
                
                data.archives.forEach(archive => {
                    const card = document.createElement('div');
                    card.className = 'archive-card';
                    
                    card.innerHTML = `
                        <div class="archive-card-header">
                            <div class="archive-user-info">
                                <div class="archive-avatar">${getInitials(archive.user_name)}</div>
                                <div class="archive-user-details">
                                    <h3>${archive.user_name}</h3>
                                    <p>${archive.user_email}</p>
                                </div>
                            </div>
                        </div>
                        <div class="archive-meta">
                            <span><i class="fas fa-clock"></i> ${formatTime(archive.archived_at)}</span>
                            <span><i class="fas fa-comment-dots"></i> ${archive.message_count} msgs</span>
                        </div>
                        <div class="archive-actions">
                            <button class="btn-view" onclick="viewArchive('${archive.user_email}', '${archive.user_name}')">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn-delete" onclick="deleteArchive('${archive.user_email}', '${archive.user_name}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    
                    archiveList.appendChild(card);
                });
            } else {
                archiveList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-archive"></i>
                        <h3 style="color: #fff; margin-top: 15px;">No archived chats</h3>
                        <p>Archived conversations will appear here</p>
                    </div>
                `;
            }
        } catch (err) {
            document.getElementById('archiveList').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                    <h3 style="color: #fff; margin-top: 15px;">Failed to load</h3>
                    <p>Please check connection or try again later</p>
                </div>
            `;
            console.error('Error loading archives:', err);
        }
    }

    async function viewArchive(userEmail, userName) {
        document.getElementById('modalUserName').innerText = `Chat with ${userName}`;
        document.getElementById('messageModal').classList.add('active');
        
        const modalMessages = document.getElementById('modalMessages');
        modalMessages.innerHTML = '<div class="text-center pt-5"><div class="spinner-border text-warning"></div></div>';
        
        try {
            const response = await fetch(ARCHIVE_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'get_archived',
                    user_email: userEmail
                })
            });
            
            const data = await response.json();
            
            if (data.status === 'success' && data.messages.length > 0) {
                modalMessages.innerHTML = '';
                
                data.messages.forEach(msg => {
                    const messageGroup = document.createElement('div');
                    messageGroup.className = `message-group ${msg.is_admin_reply == 1 ? 'admin' : 'user'}`;
                    
                    messageGroup.innerHTML = `
                        <div class="message-content-wrapper" style="width: 100%; display: flex; flex-direction: column; align-items: ${msg.is_admin_reply == 1 ? 'flex-end' : 'flex-start'};">
                            <div class="message-bubble">
                                <p style="margin: 0; line-height: 1.4;">${msg.message}</p>
                            </div>
                            <div class="message-time">${formatTime(msg.created_at)}</div>
                        </div>
                    `;
                    
                    modalMessages.appendChild(messageGroup);
                });
                
                modalMessages.scrollTop = modalMessages.scrollHeight;
            } else {
                modalMessages.innerHTML = '<div class="empty-state"><p>No messages found</p></div>';
            }
        } catch (err) {
            modalMessages.innerHTML = '<div class="empty-state text-danger"><p>Failed to load messages</p></div>';
            console.error('Error loading messages:', err);
        }
    }

    async function deleteArchive(userEmail, userName) {
        if (!confirm(`Are you sure you want to permanently delete the archived chat with ${userName}?`)) {
            return;
        }
        
        try {
            const response = await fetch('api/delete_archive.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    user_email: userEmail
                })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                // alert('Archive deleted successfully'); // Optional alert
                loadArchivedChats(); // Reload list
            } else {
                alert('Failed to delete archive: ' + (data.message || 'Unknown error'));
            }
        } catch (err) {
            alert('Failed to delete archive');
            console.error('Delete error:', err);
        }
    }

    function closeModal() {
        document.getElementById('messageModal').classList.remove('active');
    }

    // Close modal on outside click
    document.getElementById('messageModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    window.addEventListener('DOMContentLoaded', loadArchivedChats);
    </script>
</body>
</html>