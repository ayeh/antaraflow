export default function meetingLive(meetingId) {
    return {
        meetingId: meetingId,
        viewers: [],
        comments: [],
        meetingStatus: null,
        actionItems: [],
        typingUsers: [],
        isTyping: false,
        typingTimeout: null,

        init() {
            if (!window.Echo) {
                return;
            }

            this.joinPrivateChannel();
            this.joinPresenceChannel();
        },

        joinPrivateChannel() {
            window.Echo.private(`meeting.${this.meetingId}`)
                .listen('CommentAdded', (e) => {
                    this.handleCommentAdded(e);
                })
                .listen('MeetingStatusChanged', (e) => {
                    this.handleStatusChanged(e);
                })
                .listen('ActionItemUpdated', (e) => {
                    this.handleActionItemUpdated(e);
                })
                .listenForWhisper('typing', (e) => {
                    this.handleTypingIndicator(e);
                });
        },

        joinPresenceChannel() {
            window.Echo.join(`meeting.${this.meetingId}.presence`)
                .here((users) => {
                    this.viewers = users;
                })
                .joining((user) => {
                    if (!this.viewers.find(v => v.id === user.id)) {
                        this.viewers.push(user);
                    }
                })
                .leaving((user) => {
                    this.viewers = this.viewers.filter(v => v.id !== user.id);
                });
        },

        handleCommentAdded(data) {
            const exists = this.comments.find(c => c.id === data.id);
            if (!exists) {
                this.comments.push(data);
            }
        },

        handleStatusChanged(data) {
            this.meetingStatus = data.status;

            const badge = document.querySelector('[data-meeting-status]');
            if (badge) {
                badge.textContent = data.status.replace('_', ' ');
                badge.dataset.meetingStatus = data.status;
            }
        },

        handleActionItemUpdated(data) {
            const index = this.actionItems.findIndex(item => item.id === data.id);
            if (index !== -1) {
                this.actionItems[index] = { ...this.actionItems[index], ...data };
            } else {
                this.actionItems.push(data);
            }
        },

        handleTypingIndicator(data) {
            if (!this.typingUsers.find(u => u.id === data.user_id)) {
                this.typingUsers.push({ id: data.user_id, name: data.user_name });
            }

            setTimeout(() => {
                this.typingUsers = this.typingUsers.filter(u => u.id !== data.user_id);
            }, 3000);
        },

        sendTypingIndicator() {
            if (this.isTyping) {
                return;
            }

            this.isTyping = true;

            const channel = window.Echo.private(`meeting.${this.meetingId}`);
            channel.whisper('typing', {
                user_id: window.currentUserId,
                user_name: window.currentUserName,
            });

            clearTimeout(this.typingTimeout);
            this.typingTimeout = setTimeout(() => {
                this.isTyping = false;
            }, 3000);
        },

        get viewerCount() {
            return this.viewers.length;
        },

        get viewerInitials() {
            return this.viewers.map(v => {
                const parts = v.name.split(' ');
                return parts.map(p => p.charAt(0).toUpperCase()).join('').substring(0, 2);
            });
        },

        get typingText() {
            if (this.typingUsers.length === 0) {
                return '';
            }
            if (this.typingUsers.length === 1) {
                return `${this.typingUsers[0].name} is typing...`;
            }
            return `${this.typingUsers.length} people are typing...`;
        },

        destroy() {
            if (window.Echo) {
                window.Echo.leave(`meeting.${this.meetingId}`);
                window.Echo.leave(`meeting.${this.meetingId}.presence`);
            }
            clearTimeout(this.typingTimeout);
        },
    };
}
