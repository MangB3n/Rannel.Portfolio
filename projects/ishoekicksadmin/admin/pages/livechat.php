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
    <title>Live Chat - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
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

        /* CHAT WRAPPER */
        .chat-wrapper {
            background: var(--panel-bg);
            border: 1px solid rgba(181, 142, 83, 0.2);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            height: calc(100vh - 140px);
            display: flex;
            flex-direction: column;
        }

        /* HEADER */
        .chat-header {
            background: linear-gradient(135deg, #3a3a3a 0%, #4a4a4a 100%);
            padding: 15px 24px;
            border-bottom: 1px solid var(--primary-gold);
            color: var(--primary-gold);
        }

        .chat-header h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* LEFT SIDE - CONVERSATIONS */
        .conversations-panel {
            width: 350px;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            background: #353535;
        }

        .panel-header {
            padding: 16px 20px;
            background: #353535;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: var(--primary-gold);
        }

        .search-box {
            padding: 12px 16px;
            background: #353535;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 8px 16px 8px 40px;
            border: 1px solid var(--border-color);
            background: var(--bg-dark);
            border-radius: 20px;
            font-size: 14px;
            color: #fff;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-gold);
        }

        .search-icon {
            position: absolute;
            left: 28px;
            top: 22px;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        #chatList {
            overflow-y: auto;
            flex-grow: 1;
        }

        .conversation-item {
            padding: 15px 20px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s;
            background: #353535;
            color: var(--text-secondary);
        }

        .conversation-item:hover {
            background-color: var(--hover-bg);
        }

        .conversation-item.active {
            background-color: #454545;
            border-left: 3px solid var(--primary-gold);
        }

        .conversation-item .name {
            color: #fff;
            font-weight: 600;
        }
        
        .conversation-time {
            color: var(--primary-gold) !important;
            font-size: 0.75rem;
        }

        .conversation-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 12px;
            border: 2px solid #4a4a4a;
        }

        /* RIGHT SIDE - CHAT AREA */
        .chat-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-dark);
        }

        .chat-header-user {
            padding: 12px 24px;
            background: #3a3a3a;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-header-info h3 {
            color: var(--primary-gold);
            font-size: 1rem;
            margin: 0;
        }
        
        .chat-header-info p {
            color: var(--text-secondary);
        }

        #messagesList {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            background: var(--bg-dark);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* SCROLLBARS */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #2b2b2b; 
        }
        ::-webkit-scrollbar-thumb {
            background: #4a4a4a; 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-gold); 
        }

        /* MESSAGES */
        .message-group {
            display: flex;
            margin-bottom: 5px;
        }

        .message-group.user { justify-content: flex-start; }
        .message-group.admin { justify-content: flex-end; }

        .message-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
            font-weight: bold;
        }

        .message-group.user .message-avatar {
            background: #6c757d;
            margin-right: 8px;
        }

        .message-group.admin .message-avatar {
            background: var(--primary-gold);
            margin-left: 8px;
            order: 2;
        }

        .message-bubble {
            padding: 10px 16px;
            border-radius: 18px;
            position: relative;
            max-width: 100%;
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
            color: var(--text-secondary) !important;
        }

        /* INPUT AREA */
        #replyForm {
            padding: 20px;
            background: #3a3a3a;
            border-top: 1px solid var(--border-color);
        }

        .message-input-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg-dark);
            border-radius: 24px;
            padding: 8px 15px;
            border: 1px solid var(--border-color);
        }

        #messageInput {
            flex: 1;
            border: none;
            background: transparent;
            color: #fff;
            padding: 8px;
            outline: none;
        }

        #sendButton {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--primary-gradient);
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        #sendButton:hover:not(:disabled) {
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(181, 142, 83, 0.4);
        }

        #sendButton:disabled {
            background: #4a4a4a;
            color: #888;
            cursor: not-allowed;
        }

        /* EMPTY STATE */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-secondary);
            text-align: center;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: var(--primary-gold);
            opacity: 0.5;
        }

        .unread-badge {
            background: var(--primary-gold);
            color: #000;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            position: absolute;
            right: 20px;
            top: 20px;
        }

        /* Archive Button */
        #viewArchivedBtn {
            color: var(--text-secondary);
            border-color: var(--border-color);
        }
        #viewArchivedBtn:hover {
            background: var(--primary-gold);
            color: #000;
            border-color: var(--primary-gold);
        }

        @media (max-width: 768px) {
            main { margin-left: 0; padding: 15px; }
            .chat-wrapper { height: calc(100vh - 80px); }
            .conversations-panel { width: 100%; border-right: none; }
            /* JavaScript toggling needed for mobile view logic */
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <main>
        <div class="chat-wrapper">
            <div class="chat-header">
                <h1><i class="fas fa-comments"></i> Live Chat</h1>
            </div>

            <div class="chat-container">
                <div class="conversations-panel">
                    <div class="panel-header d-flex justify-content-between align-items-center">
                        <span>Messages</span>
                        <button type="button" id="viewArchivedBtn" class="btn btn-sm btn-outline-secondary" 
                                onclick="window.location.href='archived_chats.php'" 
                                style="padding: 4px 12px; font-size: 13px; border-radius: 16px;">
                            <i class="fas fa-archive"></i> Archived
                        </button>
                    </div>
                    <div class="search-box position-relative">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" placeholder="Search conversations...">
                    </div>
                    <div id="chatList">
                        <div class="empty-state">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3">Loading conversations...</p>
                        </div>
                    </div>
                </div>
                
                <div class="chat-panel">
                    <div id="chatHeaderUser" class="chat-header-user" style="display: none;">
                        <div class="position-relative">
                            <div class="chat-header-avatar" id="headerAvatar">U</div>
                            <div class="online-status"></div>
                        </div>
                        <div class="chat-header-info">
                            <h3 id="currentUserName">User Name</h3>
                            <p id="currentUserEmail">user@email.com</p>
                        </div>
                    </div>
                 
                    
                    <div id="messagesList">
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <h3>Select a conversation</h3>
                            <p>Choose a conversation from the left to start chatting</p>
                        </div>
                    </div>
                    
                    <form id="replyForm">
                        <div class="message-input-wrapper">
                            <button type="button" class="btn btn-link text-muted p-0" title="Add attachment">
                                
                            </button>
                            <input type="text" id="messageInput" placeholder="Type a message..." disabled>
                            <button type="submit" id="sendButton" disabled>
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
    const ADMIN_NAME = <?php echo json_encode($adminName); ?>;
    let selectedUserEmail = null;
    let selectedUserName = null;
    let lastMessageId = 0;
    let pollingInterval = null;

    const CHAT_LIST_API_URL = 'http://localhost/ishoekicksadmin/admin/pages/api/get_all_chats.php';
    const CHAT_API_URL = 'http://localhost/ishoekicks/live_chat.php'; 
    const GET_MESSAGES_URL = 'http://localhost/ishoekicks/get_messages.php';

    const ARCHIVE_API_URL = 'http://localhost/ishoekicksadmin/admin/pages/api/archive_chat.php';

    function getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    }

  function formatTime(timestamp) {
    const date = new Date(timestamp);

    // If date is invalid, return an empty string
    if (isNaN(date.getTime())) {
        return '';
    }

    const now = new Date();
    const diff = now - date;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));

    if (days === 0) {
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    } else if (days === 1) {
        return 'Yesterday';
    } else if (days < 7) {
        return date.toLocaleDateString('en-US', { weekday: 'short' });
    } else {
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
}
    async function fetchChatList() {
        try {
            const response = await fetch(CHAT_LIST_API_URL);
            const data = await response.json();
            const chatList = document.getElementById('chatList');
            chatList.innerHTML = '';

            if (data.status === 'success' && data.chats.length > 0) {
                data.chats.forEach(chat => {
                    const chatItem = document.createElement('div');
                    chatItem.className = 'conversation-item d-flex';
                    chatItem.dataset.email = chat.user_email;

                    const unreadBadge = chat.unread_count > 0 
                        ? `<span class="unread-badge">${chat.unread_count}</span>` 
                        : '';

                    chatItem.innerHTML = `
                        <div class="conversation-avatar">${getInitials(chat.user_name)}</div>
                        <div class="conversation-info">
                            <div class="conversation-header">
                                <span class="name">${chat.user_name}</span>
                                <span class="conversation-time">${formatTime(chat.timestamp)}</span>
                            </div>
                            <div class="last-message">${chat.last_message || 'No messages yet'}</div>
                        </div>
                        ${unreadBadge}
                    `;
                    chatItem.onclick = () => selectConversation(chat.user_email, chat.user_name);
                    chatList.appendChild(chatItem);
                });
                
                const currentChatExists = selectedUserEmail ? data.chats.some(c => c.user_email === selectedUserEmail) : false;
                if ((!selectedUserEmail || !currentChatExists) && data.chats.length > 0) {
                    selectConversation(data.chats[0].user_email, data.chats[0].user_name);
                } else {
                    document.querySelectorAll('.conversation-item').forEach(el => {
                        el.classList.toggle('active', el.dataset.email === selectedUserEmail);
                    });
                }
            } else {
                chatList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No conversations yet</h3>
                        <p>Conversations will appear here when users send messages</p>
                    </div>
                `;
            }
        } catch (err) {
            document.getElementById('chatList').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                    <h3>Connection Error</h3>
                    <p>Failed to load conversations. Please try again.</p>
                </div>
            `;
            console.error('Error loading chat list:', err);
        }
    }

    async function selectConversation(userEmail, userName) {
        if (selectedUserEmail === userEmail && document.getElementById('messagesList').children.length > 0) {
            return;
        }
        if (pollingInterval) clearInterval(pollingInterval);

        selectedUserEmail = userEmail;
        selectedUserName = userName;
        lastMessageId = 0;

        document.getElementById('chatHeaderUser').style.display = 'flex';
        document.getElementById('currentUserName').innerText = userName;
        document.getElementById('currentUserEmail').innerText = userEmail;
        document.getElementById('headerAvatar').innerText = getInitials(userName);
        document.getElementById('messageInput').disabled = false;
        document.getElementById('sendButton').disabled = false;

        document.querySelectorAll('.conversation-item').forEach(el => {
            el.classList.toggle('active', el.dataset.email === userEmail);
        });

        document.getElementById('messagesList').innerHTML = '';
        await fetchMessages(true);
        pollingInterval = setInterval(fetchMessages, 3000);
    }

    async function fetchMessages(isInitialFetch = false) {
        if (!selectedUserEmail) return;
        const messagesList = document.getElementById('messagesList');
        try {
            const response = await fetch(GET_MESSAGES_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    user_email: selectedUserEmail,
                    last_id: lastMessageId
                })
            });
            const data = await response.json();

            if (isInitialFetch) messagesList.innerHTML = '';

            if (data.status === 'success' && data.messages.length > 0) {
                const shouldScroll = (messagesList.scrollTop + messagesList.clientHeight) >= messagesList.scrollHeight - 50;

                data.messages.forEach(msg => {
                    const messageGroup = document.createElement('div');
                    messageGroup.className = `message-group ${msg.sender_type}`;
                    
                    const senderName = msg.is_admin_reply ? ADMIN_NAME : msg.user_name;
                    const initials = getInitials(senderName);
                    const messageTime = formatTime(msg.timestamp);

                    messageGroup.innerHTML = `
                        <div class="message-avatar">${initials}</div>
                        <div class="message-content-wrapper">
                            <div class="message-bubble">
                                <p class="message-text">${msg.message}</p>
                            </div>
                            <div class="message-time">${messageTime}</div>
                        </div>
                    `;

                    messagesList.appendChild(messageGroup);
                });
                lastMessageId = data.messages[data.messages.length - 1].id;
                
                if (isInitialFetch || shouldScroll) {
                    messagesList.scrollTop = messagesList.scrollHeight;
                }
            } else if (isInitialFetch && data.messages.length === 0) {
                messagesList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comment-dots"></i>
                        <h3>No messages yet</h3>
                        <p>Start the conversation by sending a message</p>
                    </div>
                `;
            }
        } catch (err) {
            if (isInitialFetch) {
                messagesList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle text-danger"></i>
                        <h3>Failed to load messages</h3>
                        <p>Please check your connection and try again</p>
                    </div>
                `;
            }
            console.error('Error loading messages:', err);
        }
    }

    document.getElementById('replyForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const messageInput = document.getElementById('messageInput');
        const message = messageInput.value.trim();
        if (!message || !selectedUserEmail) return;
        
        const sendButton = document.getElementById('sendButton');
        sendButton.disabled = true;
        sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const response = await fetch(CHAT_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'send_message',
                    user_name: ADMIN_NAME,
                    user_email: selectedUserEmail,
                    message: message,
                    is_admin: 1
                })
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                messageInput.value = '';
                await fetchMessages(false);
            } else {
                alert('Failed to send message: ' + (data.message || 'Unknown error'));
            }
        } catch (err) {
            alert('Failed to send message.');
            console.error('Send error:', err);
        }
        
        sendButton.disabled = false;
        sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
    });

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        document.querySelectorAll('.conversation-item').forEach(item => {
            const name = item.querySelector('.name').textContent.toLowerCase();
            const email = item.dataset.email.toLowerCase();
            const match = name.includes(searchTerm) || email.includes(searchTerm);
            item.style.display = match ? 'flex' : 'none';
        });
    });

    window.addEventListener('DOMContentLoaded', () => {
        fetchChatList();
        setInterval(fetchChatList, 10000);
    });


    

// Update the selectConversation function to show archive button
// Modify the existing selectConversation function around line 450
async function selectConversation(userEmail, userName) {
    if (selectedUserEmail === userEmail && document.getElementById('messagesList').children.length > 0) {
        return;
    }
    if (pollingInterval) clearInterval(pollingInterval);

    selectedUserEmail = userEmail;
    selectedUserName = userName;
    lastMessageId = 0;

    document.getElementById('chatHeaderUser').style.display = 'flex';
    document.getElementById('currentUserName').innerText = userName;
    document.getElementById('currentUserEmail').innerText = userEmail;
    document.getElementById('headerAvatar').innerText = getInitials(userName);
    document.getElementById('messageInput').disabled = false;
    document.getElementById('sendButton').disabled = false;
    
    // Show archive button
    const archiveButton = document.getElementById('archiveButton');
    if (archiveButton) {
        archiveButton.style.display = 'inline-block';
    }

    document.querySelectorAll('.conversation-item').forEach(el => {
        el.classList.toggle('active', el.dataset.email === userEmail);
    });

    document.getElementById('messagesList').innerHTML = '';
    await fetchMessages(true);
    pollingInterval = setInterval(fetchMessages, 3000);
}

// View archived chats function (optional - for viewing archived messages)
async function viewArchivedChat(userEmail) {
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

        if (data.status === 'success') {
            // Display archived messages
            console.log('Archived messages:', data.messages);
            // You can create a modal or separate page to display these
        }
    } catch (err) {
        console.error('Error fetching archived chat:', err);
    }
}
    </script>
</body>
</html>