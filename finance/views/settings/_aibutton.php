



<div id="aiFloatingBtn">

    <img
        src="/assets/images/finance/aibuttons.png"
        alt="AI Assistant"
    >

</div>





<div id="aiPopupOverlay">

    <div id="aiPopup">

        
        <aside class="ai-sidebar">

            <button
                id="newAiChatBtn"
                class="new-ai-chat-btn"
            >

                + New Chat

            </button>

            <div class="ai-sessions-label">
                Recent Conversations
            </div>

            <div
                id="aiSessionsList"
                class="ai-sessions-list"
            ></div>

            <button
                id="aiViewAllBtn"
                class="ai-view-all-btn"
                type="button"
            >

                <span>View All Conversations</span>

                <span class="ai-view-all-arrow">&#8594;</span>

            </button>

        </aside>

        
        <main class="ai-main">

            
            <div class="ai-header">

                <div class="ai-header-left">

                    <img
                        src="/assets/images/finance/aibuttons.png"
                        class="ai-logo"
                    >

                    <div>

                        <div class="ai-title">
                            Riza AI
                        </div>

                        <div class="ai-subtitle">
                            Your friendly transport assistant
                        </div>

                    </div>

                </div>

                <button id="closeAiPopup">

                    &times;

                </button>

            </div>

            
            <div
                id="aiChatBody"
                class="ai-chat-body"
            ></div>

            
            <div class="ai-input-wrapper">

                
                <div class="ai-suggestions">

                    <button class="ai-suggestion-btn">
                        Daily Report
                    </button>

                    <button class="ai-suggestion-btn">
                        Revenue Analysis
                    </button>

                    <button class="ai-suggestion-btn">
                        Fuel Costs
                    </button>

                    <button class="ai-suggestion-btn">
                        Passenger Data
                    </button>

                </div>

                
                <div class="ai-input-area">

                    <textarea
                        id="aiInput"
                        placeholder="Ask..."
                        data-placeholder="Ask..."
                    ></textarea>

                    <button id="sendAiBtn">

                        Send

                    </button>

                </div>

            </div>

        </main>

    </div>

</div>

<style>
#aiFloatingBtn {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(236, 238, 217, 0.15);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1.5px solid rgba(205, 217, 181, 0.1);
    overflow: hidden;
    cursor: pointer;
    z-index: 9999;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
    transition: all 0.35s ease;
}
#aiFloatingBtn:hover {
    transform: scale(1.1);
    box-shadow: 0 12px 44px rgba(0, 0, 0, 0.08);
}
#aiFloatingBtn img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 12px;
}

#aiPopupOverlay {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    display: none;
    align-items: flex-start;
    justify-content: flex-end;
    z-index: 10000;
    padding: 20px 20px 20px 0;
    pointer-events: none;
}
#aiPopup {
    pointer-events: auto;
}
#aiPopupOverlay.active {
    display: flex;
}
#aiFloatingBtn.hidden {
    display: none;
}

#aiPopup {
    width: 25vw;
    min-width: 200px;
    max-width: 50vw;
    height: calc(100vh - 40px);
    max-height: calc(100vh - 40px);
    background: rgba(236, 238, 217, 0.12);
    backdrop-filter: blur(40px);
    -webkit-backdrop-filter: blur(40px);
    border-radius: 16px;
    border: 1px solid rgba(205, 217, 181, 0.25);
    box-shadow:
        0 8px 40px rgba(0, 0, 0, 0.06),
        0 0 0 1px rgba(255, 255, 255, 0.05);
    overflow: hidden;
    display: flex;
    position: relative;
}
#aiPopup::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 4px;
    cursor: ew-resize;
    z-index: 10;
    background: transparent;
    transition: background 0.2s;
}
#aiPopup:hover::before {
    background: rgba(41, 87, 56, 0.08);
}
#aiPopup.resizing::before {
    background: rgba(41, 87, 56, 0.15);
}

.ai-sidebar {
    display: none;
    flex: 0 0 31%;
    min-width: 170px;
    max-width: 240px;
    background: rgba(236, 238, 217, 0.08);
    border-right: 1px solid rgba(205, 217, 181, 0.15);
    flex-direction: column;
    flex-shrink: 0;
    box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.05);
    padding: 14px 18px 16px;
    overflow: hidden;
}
.ai-sidebar.visible {
    display: flex;
}

.new-ai-chat-btn {
    width: 100%;
    margin: 0 0 12px;
    border: none;
    background: rgba(41, 87, 56, 0.8);
    color: white;
    padding: 11px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.25s ease;
    font-family: "Poppins", sans-serif;
    font-size: 14.5px;
    text-align: center;
}
.new-ai-chat-btn:hover {
    background: rgba(41, 87, 56, 0.95);
}

.ai-sessions-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: rgba(46, 72, 54, 0.55);
    padding: 0 2px 8px;
}

.ai-sessions-list {
    flex: 1;
    overflow-y: auto;
    padding: 0 2px 8px;
    min-height: 0;
}

.ai-session-item {
    display: flex;
    flex-direction: column;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(205, 217, 181, 0.12);
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 0;
}
.ai-session-item:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(41, 87, 56, 0.25);
}
.ai-session-item.active {
    background: rgba(41, 87, 56, 0.1);
    border: 1.5px solid rgba(41, 87, 56, 0.45);
    box-shadow: inset 3px 0 0 rgba(41, 87, 56, 0.55);
}
.ai-session-top {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    min-width: 0;
}
.ai-session-icon {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(41, 87, 56, 0.1);
    color: rgba(41, 87, 56, 0.85);
    display: flex;
    align-items: center;
    justify-content: center;
}
.ai-session-icon svg {
    width: 14px;
    height: 14px;
}
.ai-session-item.active .ai-session-icon {
    background: rgba(41, 87, 56, 0.18);
    color: #1f4430;
}
.ai-session-title {
    flex: 1;
    min-width: 0;
    font-size: 15px;
    font-weight: 600;
    line-height: 1.45;
    color: #2e4836;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    word-break: break-word;
    padding-top: 1px;
}
.ai-session-item.active .ai-session-title {
    font-weight: 700;
}
.ai-session-date {
    margin-left: 40px;
    font-size: 12px;
    line-height: 1.4;
    color: rgba(46, 72, 54, 0.65);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}

.ai-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.ai-header {
    background: rgba(236, 238, 217, 0.08);
    border-bottom: 1px solid rgba(205, 217, 181, 0.15);
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}
.ai-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.ai-logo {
    width: 32px;
    height: 32px;
    object-fit: contain;
}
.ai-title {
    font-size: 17px;
    font-weight: 600;
    color: #2e4836;
}
.ai-subtitle {
    font-size: 12px;
    color: rgba(46, 72, 54, 0.6);
    letter-spacing: 0.3px;
}
#closeAiPopup {
    border: none;
    background: rgba(255, 255, 255, 0.08);
    width: 30px;
    height: 30px;
    border-radius: 8px;
    font-size: 20px;
    cursor: pointer;
    color: #2e4836;
    display: flex;
    align-items: center;
    justify-content: center;
}
#closeAiPopup:hover {
    background: rgba(41, 87, 56, 0.1);
}

.ai-chat-body {
    flex: 1;
    overflow-y: auto;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-width: 0;
}
.ai-message {
    max-width: 70%;
    padding: 12px 16px;
    border-radius: 16px;
    font-size: 14px;
    line-height: 1.7;
    word-wrap: break-word;
    font-family: "Poppins", sans-serif;
    margin: 0;
}
.ai-message + .ai-message {
    margin-top: 2px;
}
.ai-message-user {
    background: rgba(41, 87, 56, 0.75);
    color: white;
    align-self: flex-end;
    border-bottom-right-radius: 4px;
    margin-left: 14px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
}
.ai-message-bot {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(205, 217, 181, 0.12);
    color: #2e4836;
    align-self: flex-start;
    border-bottom-left-radius: 4px;
    margin-right: 14px;
}

.ai-message-bot h1,
.ai-message-bot h2,
.ai-message-bot h3,
.ai-message-bot h4,
.ai-message-bot h5,
.ai-message-bot h6 {
    margin: 8px 0 3px;
    line-height: 1.5;
    font-weight: 700;
    color: #2e4836;
}
.ai-message-bot h1 {
    font-size: 16px;
}
.ai-message-bot h2 {
    font-size: 15px;
}
.ai-message-bot h3,
.ai-message-bot h4,
.ai-message-bot h5,
.ai-message-bot h6 {
    font-size: 14px;
}
.ai-message-bot p {
    margin: 5px 0;
}
.ai-message-bot ul,
.ai-message-bot ol {
    margin: 6px 0;
    padding-left: 22px;
}
.ai-message-bot li {
    margin: 4px 0;
}
.ai-message-bot ul {
    list-style: disc;
}
.ai-message-bot ol {
    list-style: decimal;
}
.ai-message-bot strong {
    font-weight: 700;
}
.ai-message-bot em {
    font-style: italic;
}
.ai-message-bot code {
    background: rgba(41, 87, 56, 0.08);
    padding: 1px 5px;
    border-radius: 4px;
    font-family: Consolas, Monaco, monospace;
    font-size: 12px;
    color: #2e4836;
}
.ai-message-bot pre {
    background: rgba(41, 87, 56, 0.08);
    padding: 10px;
    border-radius: 8px;
    overflow-x: auto;
    margin: 8px 0;
}
.ai-message-bot pre code {
    background: none;
    padding: 0;
}
.ai-md-table {
    overflow-x: auto;
    margin: 8px 0;
}
.ai-message-bot table {
    border-collapse: collapse;
    font-size: 12px;
    min-width: 100%;
}
.ai-message-bot th,
.ai-message-bot td {
    border: 1px solid rgba(41, 87, 56, 0.2);
    padding: 6px 10px;
    text-align: left;
    white-space: nowrap;
}
.ai-message-bot th {
    background: rgba(41, 87, 56, 0.12);
    font-weight: 700;
}
.ai-message-bot > *:first-child {
    margin-top: 0;
}
.ai-message-bot > *:last-child {
    margin-bottom: 0;
}

.ai-nav-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(41, 87, 56, 0.1);
    color: #1f4430;
    border: 1.5px solid rgba(41, 87, 56, 0.45);
    border-radius: 10px;
    padding: 9px 16px;
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    cursor: pointer;
    margin: 8px 0 2px;
    transition: all 0.2s ease;
}
.ai-nav-link:hover {
    background: rgba(41, 87, 56, 0.18);
    border-color: rgba(41, 87, 56, 0.7);
}

.ai-welcome {
    margin: auto 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 10px;
    padding: 12px 10px 8px;
}
.ai-welcome-logo {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: rgba(41, 87, 56, 0.08);
    border: 1px solid rgba(41, 87, 56, 0.14);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 18px rgba(41, 87, 56, 0.1);
    margin-bottom: 2px;
}
.ai-welcome-logo img {
    width: 34px;
    height: 34px;
    object-fit: contain;
}
.ai-welcome-title {
    font-size: 17px;
    font-weight: 700;
    color: #2e4836;
}
.ai-welcome-intro {
    font-size: 15px;
    color: rgba(46, 72, 54, 0.85);
    line-height: 1.8;
    max-width: 280px;
}
.ai-welcome-hint {
    font-size: 13px;
    color: rgba(46, 72, 54, 0.65);
    line-height: 1.7;
    max-width: 280px;
}
.ai-welcome-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
    max-width: 300px;
    margin-top: 14px;
}
.ai-welcome-action {
    border: 1px solid rgba(205, 217, 181, 0.3);
    background: rgba(255, 255, 255, 0.1);
    border-radius: 999px;
    padding: 11px 18px;
    cursor: pointer;
    text-align: left;
    font-family: "Poppins", sans-serif;
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
}
.ai-welcome-action:hover {
    background: rgba(41, 87, 56, 0.12);
    border-color: rgba(41, 87, 56, 0.35);
    transform: translateY(-1px);
}
.ai-welcome-action-title {
    font-size: 13px;
    font-weight: 600;
    color: #2e4836;
}
.ai-welcome-action-question {
    font-size: 12px;
    color: rgba(46, 72, 54, 0.65);
}

.ai-input-wrapper {
    border-top: 1px solid rgba(205, 217, 181, 0.15);
    flex-shrink: 0;
}
.ai-suggestions {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding: 10px 14px 0;
    scrollbar-width: none;
}
.ai-suggestions::-webkit-scrollbar {
    display: none;
}
.ai-suggestion-btn {
    flex-shrink: 0;
    border: 1px solid rgba(205, 217, 181, 0.3);
    background: rgba(255, 255, 255, 0.1);
    padding: 7px 14px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 13px;
    color: #2e4836;
    white-space: nowrap;
    font-family: "Poppins", sans-serif;
    font-weight: 500;
    transition: all 0.2s ease;
}
.ai-suggestion-btn:hover {
    background: rgba(41, 87, 56, 0.12);
    border-color: rgba(41, 87, 56, 0.3);
}
.ai-input-area {
    display: flex;
    gap: 10px;
    padding: 10px 14px 14px;
}
#aiInput {
    flex: 1;
    resize: none;
    height: 52px;
    min-height: 52px;
    max-height: 110px;
    border: 2px solid rgba(205, 217, 181, 0.5);
    background: rgba(255, 255, 255, 0.1);
    border-radius: 14px;
    padding: 12px 16px;
    outline: none;
    font-size: 14px;
    font-family: "Poppins", sans-serif;
    color: #2e4836;
    transition: border-color 0.2s;
}
#aiInput:focus {
    border-color: rgba(41, 87, 56, 0.55);
}
#aiInput::placeholder {
    color: rgba(46, 72, 54, 0.35);
    animation: askPulse 2s ease-in-out infinite;
}
@keyframes askPulse {
    0%,
    100% {
        opacity: 0.35;
    }
    50% {
        opacity: 0.85;
    }
}
#aiInput:focus {
    border-color: rgba(41, 87, 56, 0.2);
}
#sendAiBtn {
    padding: 0 26px;
    border: none;
    border-radius: 12px;
    background: rgba(41, 87, 56, 0.85);
    color: white;
    font-weight: 600;
    cursor: pointer;
    font-size: 14.5px;
    font-family: "Poppins", sans-serif;
    flex-shrink: 0;
}
#sendAiBtn:hover {
    background: rgba(41, 87, 56, 0.95);
}
#sendAiBtn:disabled {
    background: rgba(41, 87, 56, 0.45);
    cursor: not-allowed;
}

.ai-thinking {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    width: max-content;
    max-width: 70%;
}
.ai-thinking-avatar {
    flex-shrink: 0;
    width: 30px;
    height: 30px;
    padding: 4px;
    object-fit: contain;
    border-radius: 50%;
    background: rgba(41, 87, 56, 0.08);
    border: 1px solid rgba(41, 87, 56, 0.14);
}
.ai-thinking-bubble {
    display: flex;
    align-items: center;
    gap: 5px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(205, 217, 181, 0.12);
    border-radius: 16px;
    border-bottom-left-radius: 4px;
    height: 44px;
    min-width: 118px;
    max-width: 150px;
    padding: 10px 14px;
    box-sizing: border-box;
}
.ai-thinking-dot {
    width: 7px;
    height: 7px;
    background: rgba(65, 130, 80, 0.55);
    border-radius: 50%;
    animation: rizaThinking 1.2s ease-in-out infinite;
}
.ai-thinking-dot:nth-child(2) {
    animation-delay: 0.15s;
}
.ai-thinking-dot:nth-child(3) {
    animation-delay: 0.3s;
}
@keyframes rizaThinking {
    0%,
    100% {
        transform: translateY(0);
        opacity: 0.45;
    }
    40% {
        transform: translateY(-3px);
        opacity: 1;
    }
}
.ai-thinking-label {
    font-size: 13px;
    font-weight: 500;
    color: rgba(46, 72, 54, 0.7);
    margin-right: 2px;
    white-space: nowrap;
}
@media (prefers-reduced-motion: reduce) {
    .ai-thinking-dot {
        display: none;
    }
}

.ai-chat-body::-webkit-scrollbar,
.ai-sessions-list::-webkit-scrollbar {
    width: 4px;
}
.ai-chat-body::-webkit-scrollbar-thumb,
.ai-sessions-list::-webkit-scrollbar-thumb {
    background: rgba(65, 130, 80, 0.2);
    border-radius: 20px;
}

.delete-session-btn {
    flex-shrink: 0;
    width: 30px;
    height: 30px;
    border: 1px solid rgba(205, 217, 181, 0.25);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.15);
    color: rgba(46, 72, 54, 0.6);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    transition: all 0.2s ease;
}
.delete-session-btn:hover {
    background: rgba(200, 60, 50, 0.12);
    border-color: rgba(200, 60, 50, 0.3);
    color: #b3403a;
}

.ai-view-all-btn {
    flex-shrink: 0;
    width: 100%;
    margin-top: 8px;
    border: 1px solid rgba(41, 87, 56, 0.22);
    background: rgba(255, 255, 255, 0.3);
    padding: 9px 8px;
    border-radius: 9px;
    color: #2e4836;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    font-family: "Poppins", sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s ease;
}
.ai-view-all-btn:hover {
    background: rgba(41, 87, 56, 0.1);
    border-color: rgba(41, 87, 56, 0.4);
}
.ai-view-all-arrow {
    font-size: 13px;
}
.ai-session-item.ai-hidden {
    display: none;
}

@media (max-width: 768px) {
    #aiPopupOverlay {
        padding: 0;
        align-items: flex-end;
    }
    #aiPopup {
        width: 100%;
        height: 88vh;
        border-radius: 20px 20px 0 0;
        flex-direction: column;
    }
    #aiPopup {
        position: static;
    }
    #aiPopup::before {
        display: none;
    }
    .ai-sidebar.visible {
        display: flex;
        width: 100%;
        max-height: 110px;
        flex-direction: row;
        border-right: none;
        border-bottom: 1px solid rgba(205, 217, 181, 0.1);
        padding: 0;
    }
    .new-ai-chat-btn {
        margin: 10px;
        padding: 10px 16px;
        font-size: 12px;
        flex-shrink: 0;
        width: auto;
    }
    .ai-sessions-label {
        display: none;
    }
    .ai-sessions-list {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 0 10px 10px;
    }
    .ai-session-item {
        min-width: 180px;
        margin-bottom: 0;
        flex-shrink: 0;
    }
    .ai-session-item.ai-hidden {
        display: flex;
    }
    .ai-view-all-btn {
        display: none;
    }
    .ai-logo {
        width: 28px;
        height: 28px;
    }
    .ai-title {
        font-size: 13px;
    }
    .ai-welcome-logo {
        width: 46px;
        height: 46px;
    }
    .ai-welcome-logo img {
        width: 26px;
        height: 26px;
    }
    .ai-welcome-intro {
        max-width: 250px;
    }
    .ai-welcome-actions {
        max-width: 280px;
    }
    .ai-suggestions {
        padding: 10px 12px 0;
    }
    .ai-suggestion-btn {
        font-size: 12px;
        padding: 7px 14px;
    }
    .ai-input-area {
        padding: 10px 12px 14px;
    }
    #aiInput {
        height: 44px;
        min-height: 44px;
        font-size: 13.5px;
    }
    #sendAiBtn {
        font-size: 13.5px;
        padding: 0 18px;
    }
}
</style>

<script>
const aiFloatingBtn = document.getElementById("aiFloatingBtn");
const aiPopupOverlay = document.getElementById("aiPopupOverlay");
const closeAiPopup = document.getElementById("closeAiPopup");
const aiChatBody = document.getElementById("aiChatBody");
const aiInput = document.getElementById("aiInput");
const sendAiBtn = document.getElementById("sendAiBtn");
const aiSessionsList = document.getElementById("aiSessionsList");
const aiViewAllBtn = document.getElementById("aiViewAllBtn");

let currentSessionId = localStorage.getItem("finance_ai_session_id");
if (
    !currentSessionId ||
    currentSessionId === "undefined" ||
    currentSessionId === "null" ||
    currentSessionId === "0"
) {
    currentSessionId = null;
    localStorage.removeItem("finance_ai_session_id");
}

aiFloatingBtn.onclick = async function () {
    aiFloatingBtn.classList.add("hidden");
    aiPopupOverlay.classList.add("active");
    applySavedWidth();
    await loadSessions();
    if (currentSessionId) await loadChats(currentSessionId);
    else showWelcomeState();
};
closeAiPopup.onclick = function () {
    aiPopupOverlay.classList.remove("active");
    aiFloatingBtn.classList.remove("hidden");
};
aiPopupOverlay.onclick = function (e) {
    if (e.target.id === "aiPopupOverlay") {
        aiPopupOverlay.classList.remove("active");
        aiFloatingBtn.classList.remove("hidden");
    }
};

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

function formatSessionDate(str) {
    if (!str) return "";
    var m = String(str).match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    if (!m) return String(str).slice(0, 16);
    var months = [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "Sep",
        "Oct",
        "Nov",
        "Dec",
    ];
    var h = parseInt(m[4], 10);
    var ampm = h >= 12 ? "PM" : "AM";
    h = h % 12;
    if (h === 0) h = 12;
    var mm = parseInt(m[5], 10);
    return (
        months[parseInt(m[2], 10) - 1] +
        " " +
        parseInt(m[3], 10) +
        ", " +
        m[1] +
        " · " +
        h +
        ":" +
        (mm < 10 ? "0" : "") +
        mm +
        " " +
        ampm
    );
}

function renderInline(text) {
    text = escapeHtml(text);
    text = text.replace(/\[([^\]\n]+)\]\((\/[^)\s]+)\)/g, function (m, label, route) {
        return '<a class="ai-nav-link" href="' + aiTrustedHref(route) + '">' + label + "</a>";
    });
    text = text.replace(/\[([^\]\n]+)\]\((https?:\/\/[^)\s]+)\)/g, "$1");
    text = text.replace(/`([^`\n]+)`/g, "<code>$1</code>");
    text = text.replace(/\*\*([^*\n]+)\*\*/g, "<strong>$1</strong>");
    text = text.replace(/(^|\W)\*([^*\n]+)\*/g, "$1<em>$2</em>");
    return text;
}

var AI_TRUSTED_ROUTES = [
    "/finance/dashboard.php",
    "/finance/remittance.php",
    "/finance/history.php",
    "/finance/discrepancies.php",
    "/finance/online_payment_verification.php",
    "/finance/revenueOverview.php",
    "/finance/fuel_cost_analytics.php",
    "/finance/financialreports.php",
    "/finance/fleet_performance.php",
    "/finance/exportandaudits.php",
    "/finance/documentation.php",
    "/finance/downloadreports.php",
];

function aiCurrentToken() {
    var m = location.pathname.match(/^\/finance\/[^/]+\/([a-f0-9]{64})$/i);
    if (m) return m[1];
    return new URLSearchParams(location.search).get("token") || "";
}

function aiTrustedHref(route) {
    if (!/^\/finance\/[a-z0-9_-]+\.php$/i.test(route)) return route;
    var token = aiCurrentToken();
    if (token) return route.replace(/\.php$/i, "") + "/" + token;
    return route;
}

function aiNavBase(href) {
    var h = String(href).replace(/\/[a-f0-9]{64}$/i, "");
    if (!/\.php$/.test(h)) h += ".php";
    return h;
}

aiChatBody.addEventListener("click", function (e) {
    var a = e.target.closest ? e.target.closest("a.ai-nav-link") : null;
    if (!a) return;
    if (AI_TRUSTED_ROUTES.indexOf(aiNavBase(a.getAttribute("href"))) === -1) {
        e.preventDefault();
    }
});

function renderMarkdown(text) {
    var lines = String(text).replace(/\r\n/g, "\n").split("\n");
    var html = "",
        i = 0,
        listTag = null,
        listItems = [],
        para = [];

    function closeList() {
        if (listItems.length) {
            html += "<" + listTag + ">" + listItems.join("") + "</" + listTag + ">";
            listItems = [];
            listTag = null;
        }
    }
    function flushPara() {
        if (para.length) {
            html += "<p>" + para.map(renderInline).join("<br>") + "</p>";
            para = [];
        }
    }
    function isTableSep(line) {
        return /^\|[\s:|-]+\|$/.test(line) && line.indexOf("-") !== -1;
    }

    while (i < lines.length) {
        var line = lines[i];
        var trimmed = line.trim();

        if (/^```/.test(trimmed)) {
            flushPara();
            closeList();
            var code = [];
            i++;
            while (i < lines.length && !/^```/.test(lines[i].trim())) {
                code.push(lines[i]);
                i++;
            }
            i++;
            html += "<pre><code>" + escapeHtml(code.join("\n")) + "</code></pre>";
            continue;
        }

        if (/^#{1,6}\s+/.test(trimmed)) {
            flushPara();
            closeList();
            var m = trimmed.match(/^(#{1,6})\s+(.*)$/);
            html += "<h" + m[1].length + ">" + renderInline(m[2]) + "</h" + m[1].length + ">";
            i++;
            continue;
        }

        if (/^\|/.test(trimmed) && i + 1 < lines.length && isTableSep(lines[i + 1].trim())) {
            flushPara();
            closeList();
            var rows = [];
            while (i < lines.length && /^\|/.test(lines[i].trim()) && lines[i].trim() !== "|") {
                if (!isTableSep(lines[i].trim())) {
                    rows.push(
                        lines[i]
                            .trim()
                            .replace(/^\||\|$/g, "")
                            .split("|")
                            .map(function (c) {
                                return renderInline(c.trim());
                            }),
                    );
                }
                i++;
                if (i < lines.length && isTableSep(lines[i].trim())) i++;
            }
            if (rows.length) {
                var thead = rows[0]
                    .map(function (c) {
                        return "<th>" + c + "</th>";
                    })
                    .join("");
                var tbody = rows
                    .slice(1)
                    .map(function (r) {
                        return (
                            "<tr>" +
                            r
                                .map(function (c) {
                                    return "<td>" + c + "</td>";
                                })
                                .join("") +
                            "</tr>"
                        );
                    })
                    .join("");
                html +=
                    '<div class="ai-md-table"><table><thead><tr>' +
                    thead +
                    "</tr></thead><tbody>" +
                    tbody +
                    "</tbody></table></div>";
            }
            continue;
        }

        if (/^[-*+]\s+/.test(trimmed)) {
            flushPara();
            if (listTag !== "ul") {
                closeList();
                listTag = "ul";
            }
            listItems.push("<li>" + renderInline(trimmed.replace(/^[-*+]\s+/, "")) + "</li>");
            i++;
            continue;
        }

        if (/^\d+\.\s+/.test(trimmed)) {
            flushPara();
            if (listTag !== "ol") {
                closeList();
                listTag = "ol";
            }
            listItems.push("<li>" + renderInline(trimmed.replace(/^\d+\.\s+/, "")) + "</li>");
            i++;
            continue;
        }

        if (trimmed === "") {
            flushPara();
            closeList();
            i++;
            continue;
        }

        closeList();
        para.push(line);
        i++;
    }

    flushPara();
    closeList();
    return html;
}

function appendMessage(message, type = "bot") {
    var div = document.createElement("div");
    div.className = "ai-message ai-message-" + type;
    div.innerHTML =
        type === "bot" ? renderMarkdown(message) : escapeHtml(message).replace(/\n/g, "<br>");
    aiChatBody.appendChild(div);
    aiChatBody.scrollTop = aiChatBody.scrollHeight;
}

function showWelcomeState() {
    aiChatBody.innerHTML =
        '<div class="ai-welcome">' +
        '<div class="ai-welcome-logo">' +
        '<img src="/assets/images/finance/aibuttons.png" alt="Riza">' +
        "</div>" +
        '<div class="ai-welcome-title">Meet Riza</div>' +
        '<div class="ai-welcome-intro">Your AI transportation assistant for the <strong>Rizal Public Market Vendors Farmers Multi-Purpose Cooperative</strong>.</div>' +
        '<div class="ai-welcome-hint">Ask me about your transportation data, drivers, trips, passengers, or revenue.</div>' +
        '<div class="ai-welcome-actions">' +
        '<button type="button" class="ai-welcome-action" data-question="Who is the highest performing driver?">' +
        '<span class="ai-welcome-action-title">Driver Performance</span>' +
        '<span class="ai-welcome-action-question">Who is the highest performing driver?</span>' +
        "</button>" +
        '<button type="button" class="ai-welcome-action" data-question="How much revenue did we generate?">' +
        '<span class="ai-welcome-action-title">Revenue</span>' +
        '<span class="ai-welcome-action-question">How much revenue did we generate?</span>' +
        "</button>" +
        '<button type="button" class="ai-welcome-action" data-question="Show me this year\'s trip data.">' +
        '<span class="ai-welcome-action-title">Trip Overview</span>' +
        '<span class="ai-welcome-action-question">Show me this year\'s trip data.</span>' +
        "</button>" +
        "</div>" +
        "</div>";
    aiChatBody.querySelectorAll(".ai-welcome-action").forEach(function (btn) {
        btn.onclick = function () {
            aiInput.value = btn.dataset.question;
            btn.blur();
            sendAiMessage();
        };
    });
    aiChatBody.scrollTop = 0;
}

function sessionIconSvg(title) {
    var t = " " + String(title || "").toLowerCase() + " ";
    var svg =
        '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    var inner;
    if (/revenue|payment|remittance|fare|amount|salary/.test(t)) {
        inner =
            '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>';
    } else if (/driver/.test(t)) {
        inner =
            '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>';
    } else if (/passenger/.test(t)) {
        inner =
            '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>';
    } else if (/trip|bus|route|travel/.test(t)) {
        inner =
            '<rect x="4" y="3" width="16" height="12" rx="2"/><path d="M4 9h16"/><path d="M8 20v-2"/><path d="M16 20v-2"/><circle cx="8" cy="20" r="1.6"/><circle cx="16" cy="20" r="1.6"/>';
    } else if (/fuel|cost|expense|expenditure|maintenance/.test(t)) {
        inner = '<path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>';
    } else {
        inner =
            '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>';
    }
    return svg + inner + "</svg>";
}

var aiAllExpanded = false;

async function loadSessions() {
    try {
        var res = await fetch("/finance/api/ai/get_sessions.php");
        var data = await res.json();
        if (!data.success) return;
        aiSessionsList.innerHTML = "";
        var sessions = data.sessions || [];
        sessions.forEach(function (session) {
            var div = document.createElement("div");
            div.className = "ai-session-item";
            if (String(currentSessionId) === String(session.id)) div.classList.add("active");
            div.innerHTML =
                '<div class="ai-session-top">' +
                '<span class="ai-session-icon">' +
                sessionIconSvg(session.session_title) +
                "</span>" +
                '<div class="ai-session-title"></div>' +
                '<button class="delete-session-btn" data-id="' +
                session.id +
                '" type="button" title="Delete conversation"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>' +
                "</div>" +
                '<div class="ai-session-date"></div>';
            div.querySelector(".ai-session-title").textContent =
                session.session_title || "New Conversation";
            div.querySelector(".ai-session-date").textContent = formatSessionDate(
                session.created_at,
            );
            div.addEventListener("click", async function (e) {
                if (e.target.closest(".delete-session-btn")) return;
                currentSessionId = session.id;
                localStorage.setItem("finance_ai_session_id", currentSessionId);
                await loadChats(session.id);
                loadSessions();
            });
            var del = div.querySelector(".delete-session-btn");
            del.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                var sid = del.dataset.id;
                showConfirm(
                    "Delete Conversation?",
                    "This chat history will be permanently removed.",
                    function () {
                        fetch("/finance/api/ai/delete_session.php", {
                            method: "POST",
                            credentials: "same-origin",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({ session_id: Number(sid) }),
                        })
                            .then(function (r) {
                                return r.json();
                            })
                            .then(function (d) {
                                if (d.success) {
                                    if (String(currentSessionId) === String(sid)) {
                                        currentSessionId = null;
                                        localStorage.removeItem("finance_ai_session_id");
                                        showWelcomeState();
                                    }
                                    div.remove();
                                    showAlert("success", "Conversation deleted");
                                }
                            })
                            .catch(function () {
                                showAlert(
                                    "error",
                                    "Delete Failed",
                                    "Unable to delete conversation.",
                                );
                            });
                    },
                );
            });
            aiSessionsList.appendChild(div);
        });
        var items = aiSessionsList.querySelectorAll(".ai-session-item");
        var showViewAll = sessions.length > 8 && window.innerWidth > 768;
        if (aiViewAllBtn) {
            aiViewAllBtn.style.display = showViewAll ? "" : "none";
            aiViewAllBtn.onclick = function () {
                aiAllExpanded = !aiAllExpanded;
                items.forEach(function (it, idx) {
                    it.classList.toggle("ai-hidden", idx >= 8 && !aiAllExpanded);
                });
                aiViewAllBtn.innerHTML = aiAllExpanded
                    ? '<span>Show Recent</span><span class="ai-view-all-arrow">&#8592;</span>'
                    : '<span>View All Conversations</span><span class="ai-view-all-arrow">&#8594;</span>';
            };
        }
        items.forEach(function (it, idx) {
            it.classList.toggle("ai-hidden", showViewAll && idx >= 8 && !aiAllExpanded);
        });
    } catch (e) {}
}

async function loadChats(sessionId) {
    try {
        currentSessionId = sessionId;
        localStorage.setItem("finance_ai_session_id", currentSessionId);
        var res = await fetch("/finance/api/ai/get_chats.php?session_id=" + sessionId);
        var data = await res.json();
        if (!data.success) return;
        aiChatBody.innerHTML = "";
        if (!data.chats || data.chats.length === 0) {
            showWelcomeState();
            return;
        }
        data.chats.forEach(function (chat) {
            appendMessage(chat.user_message, "user");
            appendMessage(chat.ai_response, "bot");
        });
    } catch (e) {
        console.error("LOAD CHAT ERROR:", e);
    }
}

var aiThinkingActive = null;
var aiRequestSeq = 0;
var AI_THINKING_MIN_MS = 5000;

function showThinking() {
    hideThinking();
    var div = document.createElement("div");
    div.className = "ai-thinking";
    div.setAttribute("role", "status");
    div.setAttribute("aria-label", "Riza is thinking");
    div.innerHTML =
        '<img class="ai-thinking-avatar" src="/assets/images/finance/aibuttons.png" alt="Riza">' +
        '<div class="ai-thinking-bubble">' +
        '<span class="ai-thinking-label">Thinking</span>' +
        '<span class="ai-thinking-dot"></span>' +
        '<span class="ai-thinking-dot"></span>' +
        '<span class="ai-thinking-dot"></span>' +
        "</div>";
    aiChatBody.appendChild(div);
    aiChatBody.scrollTop = aiChatBody.scrollHeight;
    aiThinkingActive = div;
}

function hideThinking() {
    if (aiThinkingActive) {
        aiThinkingActive.remove();
        aiThinkingActive = null;
    }
}

function showAiError() {
    hideThinking();
    appendMessage("**I couldn't complete that request right now. Please try again.**");
}

async function sendAiMessage() {
    var msg = aiInput.value.trim();
    if (!msg || aiThinkingActive) return;
    var requestId = ++aiRequestSeq;
    var thinkingStart = Date.now();
    appendMessage(msg, "user");
    aiInput.value = "";
    showThinking();
    sendAiBtn.disabled = true;
    var controller = new AbortController();
    var timeout = setTimeout(function () {
        controller.abort();
    }, 60000);
    try {
        var res = await fetch("/finance/api/ai/chat.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ session_id: currentSessionId, message: msg }),
            signal: controller.signal,
        });
        var data = await res.json();
        clearTimeout(timeout);
        if (requestId !== aiRequestSeq) return;
        var remain = AI_THINKING_MIN_MS - (Date.now() - thinkingStart);
        if (remain > 0) {
            await new Promise(function (resolve) {
                setTimeout(resolve, remain);
            });
        }
        hideThinking();
        if (data.session_id) {
            currentSessionId = data.session_id;
            localStorage.setItem("finance_ai_session_id", currentSessionId);
        }
        var reply = data.success ? (data.reply || data.response || "").trim() : "";
        if (reply) {
            appendMessage(reply);
        } else {
            showAiError();
        }
        await loadSessions();
    } catch (e) {
        clearTimeout(timeout);
        if (requestId !== aiRequestSeq) return;
        hideThinking();
        showAiError();
    } finally {
        if (requestId === aiRequestSeq) {
            sendAiBtn.disabled = false;
        }
    }
}

sendAiBtn.onclick = sendAiMessage;
aiInput.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        sendAiMessage();
    }
});

document.querySelectorAll(".ai-suggestion-btn").forEach(function (b) {
    b.onclick = function () {
        aiInput.value = this.innerText;
        b.blur();
        sendAiMessage();
    };
});

document.getElementById("newAiChatBtn").onclick = async function () {
    currentSessionId = null;
    localStorage.removeItem("finance_ai_session_id");
    showWelcomeState();
    await loadSessions();
};

window.addEventListener("load", async function () {
    if (currentSessionId) {
        await loadSessions();
        await loadChats(currentSessionId);
    }
});

(function () {
    var inp = document.getElementById("aiInput");
    if (!inp) return;
    var dots = 0;
    setInterval(function () {
        if (inp === document.activeElement) return;
        dots = (dots + 1) % 4;
        inp.placeholder = "Ask" + ".".repeat(dots) + " ".repeat(3 - dots);
    }, 500);
})();

(function () {
    var p = document.getElementById("aiPopup"),
        s = document.querySelector(".ai-sidebar");
    if (!p) return;
    function toggle() {
        if (!s) return;
        if (p.offsetWidth >= window.innerWidth * 0.3) s.classList.add("visible");
        else s.classList.remove("visible");
    }
    window.applySavedWidth = function () {
        var saved = localStorage.getItem("ai_popup_w");
        if (saved) {
            p.style.width = saved;
        }
        setTimeout(toggle, 50);
    };
    if (window.innerWidth > 768) {
        var drag = false,
            sx,
            sw;
        p.addEventListener("mousedown", function (e) {
            if (e.offsetX > 8) return;
            drag = true;
            sx = e.clientX;
            sw = p.offsetWidth;
            p.classList.add("resizing");
            e.preventDefault();
        });
        document.addEventListener("mousemove", function (e) {
            if (!drag) return;
            var w = sw + (sx - e.clientX);
            if (w < 200) w = 200;
            if (w > window.innerWidth * 0.5) w = window.innerWidth * 0.5;
            p.style.width = w + "px";
            toggle();
        });
        document.addEventListener("mouseup", function () {
            if (!drag) return;
            drag = false;
            p.classList.remove("resizing");
            localStorage.setItem("ai_popup_w", p.style.width);
        });
    }
    if (typeof ResizeObserver !== "undefined") {
        var ro = new ResizeObserver(function () {
            toggle();
        });
        ro.observe(p);
    }
})();
</script>
