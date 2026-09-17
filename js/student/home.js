(() => {
    'use strict';

    const API_URL = 'api/student-home.php';
    const SESSION_URL = 'api/auth.php?action=session';
    const LOGOUT_URL = 'api/auth.php?action=logout';
    const DEFAULT_FILE_LABEL = 'Add a photo or video';

    const state = {
        csrfToken: '',
        userId: 0,
        previewUrl: null,
        activeFeature: 0,
        featureTimer: null,
    };

    const select = (selector, root = document) => root.querySelector(selector);
    const selectAll = (selector, root = document) => [...root.querySelectorAll(selector)];

    const createElement = (tag, className, text) => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    };

    const showToast = (message, type = 'success') => {
        const toast = select('[data-toast]');
        if (!toast) return;

        toast.textContent = message;
        toast.classList.remove('hidden', 'bg-[#397565]', 'bg-[#c84510]');
        toast.classList.add(type === 'error' ? 'bg-[#c84510]' : 'bg-[#397565]');
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => toast.classList.add('hidden'), 4500);
    };

    const formatDate = value => {
        if (!value) return '';
        return new Intl.DateTimeFormat('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date(value.replace(' ', 'T')));
    };

    const timeAgo = value => {
        if (!value) return 'Recently';
        const difference = Date.now() - new Date(value.replace(' ', 'T')).getTime();
        const minutes = Math.max(1, Math.floor(difference / 60000));
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.floor(hours / 24);
        return `${days}d ago`;
    };

    const initializeMenus = () => {
        const menus = selectAll('[data-notification-menu], [data-account-menu]');
        menus.forEach(menu => menu.addEventListener('toggle', () => {
            if (!menu.open) return;
            menus.forEach(other => {
                if (other !== menu) other.removeAttribute('open');
            });
        }));

        document.addEventListener('click', event => {
            menus.forEach(menu => {
                if (menu.open && !menu.contains(event.target)) menu.removeAttribute('open');
            });
        });
    };

    const initializeSidebar = () => {
        const sidebar = select('[data-student-sidebar]');
        const toggle = select('[data-student-sidebar-toggle]');
        const content = selectAll('[data-student-sidebar-content]');
        if (!sidebar || !toggle) return;

        const setExpanded = expanded => {
            sidebar.classList.toggle('w-64', expanded);
            sidebar.classList.toggle('w-20', !expanded);
            select('[data-sidebar-header]', sidebar)?.classList.toggle('justify-between', expanded);
            selectAll('[data-sidebar-label]', sidebar).forEach(label => {
                label.classList.toggle('hidden', !expanded);
            });
            content.forEach(element => {
                element.classList.toggle('lg:ml-64', expanded);
                element.classList.toggle('lg:ml-20', !expanded);
            });
            toggle.setAttribute('aria-expanded', String(expanded));
            toggle.setAttribute('aria-label', expanded ? 'Collapse navigation' : 'Expand navigation');
        };

        setExpanded(false);
        toggle.addEventListener('click', () => {
            setExpanded(toggle.getAttribute('aria-expanded') !== 'true');
        });
    };

    const clearMedia = () => {
        const imageInput = select('[data-image-input]');
        const videoInput = select('[data-video-input]');
        const preview = select('[data-post-media-preview]');
        const image = select('[data-media-preview-image]');
        const video = select('[data-media-preview-video]');
        const error = select('[data-post-media-error]');
        const fileName = select('[data-file-name]');

        if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
        state.previewUrl = null;
        if (imageInput) imageInput.value = '';
        if (videoInput) videoInput.value = '';
        if (image) image.src = '';
        if (video) {
            video.pause();
            video.removeAttribute('src');
            video.load();
        }
        image?.classList.add('hidden');
        video?.classList.add('hidden');
        preview?.classList.add('hidden');
        error?.classList.add('hidden');
        if (fileName) fileName.textContent = DEFAULT_FILE_LABEL;
    };

    const mediaError = message => {
        clearMedia();
        const error = select('[data-post-media-error]');
        if (!error) return;
        error.textContent = message;
        error.classList.remove('hidden');
    };

    const showMediaPreview = (file, type) => {
        const image = select('[data-media-preview-image]');
        const video = select('[data-media-preview-video]');
        select('[data-file-name]').textContent = file.name;
        state.previewUrl = URL.createObjectURL(file);

        if (type === 'video') {
            select('[data-media-preview-title]').textContent = 'Video preview';
            select('[data-media-preview-help]').textContent = 'Your video will be playable in the feed';
            video.src = state.previewUrl;
            video.classList.remove('hidden');
            image.classList.add('hidden');
            select('[data-post-media-preview]').classList.remove('hidden');
            return;
        }

        const checker = new Image();
        checker.onload = () => {
            if (checker.naturalWidth > 4096 || checker.naturalHeight > 4096) {
                mediaError('Choose an image no larger than 4096 × 4096 pixels.');
                return;
            }
            select('[data-media-preview-title]').textContent = 'Image preview';
            select('[data-media-preview-help]').textContent = 'Full image inside a 4:5 portrait frame';
            image.src = state.previewUrl;
            image.classList.remove('hidden');
            video.classList.add('hidden');
            select('[data-post-media-preview]').classList.remove('hidden');
        };
        checker.src = state.previewUrl;
    };

    const initializeMediaPicker = () => {
        const imageInput = select('[data-image-input]');
        const videoInput = select('[data-video-input]');
        if (!imageInput || !videoInput) return;

        imageInput.addEventListener('change', () => {
            const file = imageInput.files?.[0];
            if (!file) {
                clearMedia();
                return;
            }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                mediaError('Use a JPG, PNG, or WebP image.');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                mediaError('Choose an image no larger than 5 MB.');
                return;
            }
            videoInput.value = '';
            if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
            showMediaPreview(file, 'image');
        });

        videoInput.addEventListener('change', () => {
            const file = videoInput.files?.[0];
            if (!file) {
                clearMedia();
                return;
            }
            if (!['video/mp4', 'video/webm', 'video/quicktime'].includes(file.type)) {
                mediaError('Use an MP4, WebM, or MOV video.');
                return;
            }
            if (file.size > 25 * 1024 * 1024) {
                mediaError('Choose a video no larger than 25 MB.');
                return;
            }
            imageInput.value = '';
            if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
            showMediaPreview(file, 'video');
        });

        select('[data-post-media-remove]')?.addEventListener('click', clearMedia);
    };

    const renderStudent = student => {
        selectAll('[data-student-name]').forEach(element => {
            element.textContent = student.full_name;
        });
        selectAll('[data-student-initials]').forEach(element => {
            element.textContent = student.initials || 'ST';
        });
        const email = select('[data-student-email]');
        if (email) email.textContent = student.email;
    };

    const renderNotifications = (notifications, count) => {
        const badge = select('[data-notification-count]');
        const summary = select('[data-notification-summary]');
        const list = select('[data-notification-list]');
        const markAll = select('[data-mark-all-notifications]');
        badge.textContent = count > 9 ? '9+' : String(count);
        badge.classList.toggle('hidden', count === 0);
        badge.classList.toggle('grid', count > 0);
        markAll.classList.toggle('hidden', count === 0);
        summary.textContent = count === 0
            ? 'No unread notifications'
            : `${count} unread ${count === 1 ? 'notification' : 'notifications'}`;

        if (!notifications.length) {
            list.innerHTML = `
                <div class="px-6 py-10 text-center" data-notification-empty>
                    <span class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-[#121017]/7 text-[#121017]/45">
                        <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 22a2.5 2.5 0 0 0 2.35-1.65h-4.7A2.5 2.5 0 0 0 12 22Zm7-6.5-1.5-2V9a5.5 5.5 0 0 0-4.25-5.35V3a1.25 1.25 0 0 0-2.5 0v.65A5.5 5.5 0 0 0 6.5 9v4.5l-1.5 2V18h14v-2.5Z"></path>
                        </svg>
                    </span>
                    <strong class="mt-3 block text-sm">No notifications yet</strong>
                    <span class="mt-1 block text-[11px] text-[#121017]/45">Review updates will appear here.</span>
                </div>`;
            return;
        }

        list.replaceChildren(...notifications.map(notification => {
            const item = createElement(
                'article',
                `flex gap-3 border-b border-[#121017]/8 px-4 py-3 last:border-b-0 ${notification.is_read ? 'bg-white' : 'bg-[#C6F24E]/10'}`
            );
            const dot = createElement(
                'span',
                `mt-1.5 h-2 w-2 shrink-0 rounded-full ${notification.is_read ? 'bg-[#121017]/15' : 'bg-[#397565]'}`
            );
            const content = createElement('div', 'min-w-0 flex-1');
            content.appendChild(createElement('p', 'text-xs font-bold leading-5', notification.message));
            content.appendChild(createElement('time', 'mt-1 block text-[10px] text-[#121017]/40', timeAgo(notification.created_at)));
            item.append(dot, content);

            if (!notification.is_read) {
                const button = createElement('button', 'shrink-0 self-center text-[10px] font-black text-[#397565] hover:underline', 'Mark read');
                button.type = 'button';
                button.dataset.markNotification = notification.id;
                button.setAttribute('aria-label', `Mark notification as read: ${notification.message}`);
                item.appendChild(button);
            }
            return item;
        }));
    };

    const markNotificationsRead = async notificationId => {
        await axios.post(API_URL, {
            action: 'mark_notifications_read',
            ...(notificationId ? { notification_id: notificationId } : {}),
        }, {
            headers: { 'X-CSRF-Token': state.csrfToken },
        });
        await loadPage();
    };

    const initializeNotifications = () => {
        select('[data-mark-all-notifications]')?.addEventListener('click', async event => {
            event.currentTarget.disabled = true;
            try {
                await markNotificationsRead();
            } catch (error) {
                showToast(error.response?.data?.message || 'Notifications could not be updated.', 'error');
            } finally {
                event.currentTarget.disabled = false;
            }
        });

        select('[data-notification-list]')?.addEventListener('click', async event => {
            const button = event.target.closest('[data-mark-notification]');
            if (!button) return;
            button.disabled = true;
            try {
                await markNotificationsRead(button.dataset.markNotification);
            } catch (error) {
                button.disabled = false;
                showToast(error.response?.data?.message || 'The notification could not be updated.', 'error');
            }
        });
    };

    const renderSubmissions = submissions => {
        const card = select('[data-submissions-card]');
        if (!submissions.length) {
            card.classList.add('hidden');
            return;
        }

        const pending = submissions.filter(post => post.status === 'pending').length;
        const rejected = submissions.filter(post => post.status === 'rejected').length;
        select('[data-submission-count]').textContent = String(submissions.length);
        select('[data-submission-summary]').textContent = `${pending} waiting · ${rejected} rejected`;
        card.classList.remove('hidden');
    };

    const renderCurrentEvent = event => {
        const container = select('[data-current-event]');
        if (!event) return;

        container.className = 'overflow-hidden rounded-xl border border-[#121017]/8 bg-white shadow-[0_12px_35px_rgba(18,16,23,.05)]';
        container.replaceChildren();

        if (event.poster_path) {
            const image = createElement('img', 'h-40 w-full object-cover');
            image.src = event.poster_path;
            image.alt = `${event.title} poster`;
            container.appendChild(image);
        } else {
            container.appendChild(createElement(
                'div',
                'grid h-28 place-items-center bg-[#397565] text-5xl font-black text-white/20',
                'CITE'
            ));
        }

        const content = createElement('div', 'p-5');
        const labelRow = createElement('div', 'flex items-center justify-between gap-2');
        labelRow.appendChild(createElement(
            'span',
            'text-[9px] font-black uppercase tracking-wider text-[#397565]',
            'Current event'
        ));
        labelRow.appendChild(createElement(
            'span',
            'rounded-full bg-[#C6F24E]/35 px-2.5 py-1 text-[9px] font-black uppercase text-[#397565]',
            event.schedule_state
        ));
        content.appendChild(labelRow);
        content.appendChild(createElement('h2', 'mt-2 text-xl font-black', event.title));
        content.appendChild(createElement(
            'p',
            'mt-2 text-xs leading-5 text-[#121017]/50',
            `${formatDate(event.start_at)} · ${event.location || 'CITE Campus'}`
        ));
        container.appendChild(content);
    };

    const featureSlide = event => {
        const article = createElement(
            'article',
            'relative min-h-60 overflow-hidden p-5 sm:min-h-68 sm:p-6'
        );
        article.dataset.featureSlide = '';

        if (event.poster_path) {
            article.style.backgroundImage = `linear-gradient(90deg, rgba(18,16,23,.94), rgba(18,16,23,.48)), url("${event.poster_path}")`;
            article.style.backgroundPosition = 'center';
            article.style.backgroundSize = 'cover';
        } else {
            article.style.backgroundImage = 'radial-gradient(circle at 85% 20%, rgba(198,242,78,.25), transparent 30%), linear-gradient(135deg, #397565, #121017 72%)';
        }

        const content = createElement('div', 'relative flex min-h-52 max-w-2xl flex-col justify-end sm:min-h-56');
        const badges = createElement('div', 'mb-auto flex flex-wrap items-center gap-2');
        badges.appendChild(createElement(
            'span',
            'rounded-full bg-[#C6F24E] px-3 py-1 text-[9px] font-black uppercase tracking-wider text-[#121017]',
            event.schedule_state
        ));
        if (event.type) {
            badges.appendChild(createElement(
                'span',
                'rounded-full bg-white/15 px-3 py-1 text-[9px] font-black uppercase tracking-wider',
                event.type
            ));
        }
        content.appendChild(badges);
        content.appendChild(createElement('h3', 'text-3xl font-black tracking-[-.04em] sm:text-4xl', event.title));
        if (event.description) {
            content.appendChild(createElement('p', 'mt-2 text-sm leading-6 text-white/70', event.description));
        }
        content.appendChild(createElement(
            'p',
            'mt-3 text-xs font-bold text-white/75',
            `${formatDate(event.start_at)} · ${event.location || 'CITE Campus'}`
        ));
        article.appendChild(content);
        return article;
    };

    const showFeature = index => {
        const slides = selectAll('[data-feature-slide]');
        if (!slides.length) return;
        state.activeFeature = (index + slides.length) % slides.length;
        slides.forEach((slide, slideIndex) => {
            slide.classList.toggle('hidden', slideIndex !== state.activeFeature);
        });
    };

    const renderFeaturedEvents = events => {
        select('[data-featured-count]').textContent = `${events.length} featured`;
        if (!events.length) return;

        const content = select('[data-featured-content]');
        content.replaceChildren(...events.map(featureSlide));
        showFeature(0);

        window.clearInterval(state.featureTimer);
        if (events.length > 1) {
            state.featureTimer = window.setInterval(() => showFeature(state.activeFeature + 1), 7000);
        }
    };

    const feedIcon = name => {
        const paths = {
            like: '<path d="M7 10v11H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3Zm0 0 4-8a3 3 0 0 1 2 3v4h6a3 3 0 0 1 2.9 3.7l-1.7 7A3 3 0 0 1 17.3 22H7"/>',
            love: '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
            celebrate: '<path d="m4 20 6-2-4-4-2 6Zm2-6 3-3m5-7 1-2m4 8 3-1m-7 5 1 3M9 3l2 2m7 1 2-2M11 9l4-2 2 4-4 2-2-4Z"/>',
            support: '<path d="M12 21s-8-4.7-8-10a4 4 0 0 1 7-2.5L12 10l1-1.5A4 4 0 0 1 20 11c0 5.3-8 10-8 10Z"/><path d="M8 15h8m-4-4v8"/>',
            comment: '<path d="M20 11.5a8.5 8.5 0 0 1-8.5 8.5 9 9 0 0 1-4-.9L3 21l1.3-4.3A8.5 8.5 0 1 1 20 11.5Z"/><path d="M8 11h8m-8 3h5"/>',
            pin: '<path d="m8 3 8 8m-7-7 3-1 6 6-1 3-3 1-3 6-2-2 6-3M5 21l5-5"/>'
        };
        return `<svg class="h-4 w-4 shrink-0 fill-none stroke-current stroke-[1.8]" viewBox="0 0 24 24" aria-hidden="true">${paths[name]}</svg>`;
    };
    const feedAction = async (payload, failure) => {
        try {
            await axios.post(API_URL, payload, { headers: { 'X-CSRF-Token': state.csrfToken } });
            await loadPage();
        } catch (error) {
            showToast(error.response?.data?.message || failure, 'error');
        }
    };

    const feedPost = post => {
        const article = createElement(
            'article',
            'rounded-xl border border-[#397565]/15 bg-white shadow-[0_14px_38px_rgba(18,16,23,.07)]'
        );
        const header = createElement('header', 'flex items-center gap-3 p-4');
        header.appendChild(createElement(
            'span',
            'grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#C6F24E] text-xs font-black text-[#121017] ring-2 ring-[#397565]/10',
            post.author_initials
        ));

        const identity = createElement('div', 'min-w-0 flex-1');
        const authorRow = createElement('div', 'flex flex-wrap items-center gap-2');
        authorRow.appendChild(createElement('strong', 'truncate text-sm', post.author_name));
        if (post.is_official) {
            authorRow.appendChild(createElement(
                'span',
                'rounded-full bg-[#C6F24E]/40 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-[#397565]',
                'Official announcement'
            ));
        }
        if (post.author_role === 'Faculty') authorRow.appendChild(createElement('span', 'rounded bg-[#397565]/10 px-2 py-0.5 text-[10px] font-black text-[#397565]', 'Faculty'));
        identity.appendChild(authorRow);
        identity.appendChild(createElement(
            'time',
            'block text-[10px] text-[#121017]/40',
            timeAgo(post.reviewed_at || post.created_at)
        ));
        header.appendChild(identity);
        article.appendChild(header);

        const body = createElement('div', 'px-4 pb-4');
        body.appendChild(createElement('p', 'whitespace-pre-line text-sm leading-6', post.content));
        article.appendChild(body);

        if (post.image_path) {
            const frame = createElement('div', 'mx-auto aspect-[4/5] max-h-[590px] w-full max-w-[470px] bg-[#121017]');
            const image = createElement('img', 'h-full w-full object-contain');
            image.src = post.image_path;
            image.alt = `${post.author_name} post image`;
            frame.appendChild(image);
            article.appendChild(frame);
        } else if (post.video_path) {
            const frame = createElement('div', 'mx-auto aspect-[4/5] max-h-[590px] w-full max-w-[470px] bg-[#121017]');
            const video = createElement('video', 'h-full w-full object-contain');
            video.src = post.video_path;
            video.controls = true;
            video.preload = 'metadata';
            video.playsInline = true;
            frame.appendChild(video);
            article.appendChild(frame);
        }

        const engagement = createElement('div', 'px-4 pb-4');
        const counts = createElement('div', 'flex items-center justify-between border-t border-[#397565]/10 py-3 text-xs text-[#121017]/55');
        const reactions = createElement('span', 'flex items-center gap-2');
        const reactionNames = { like: 'Like', love: 'Love', celebrate: 'Celebrate', support: 'Support' };
        for (const [type, total] of Object.entries(post.reaction_counts || {})) {
            const badge = createElement('span', 'inline-flex items-center gap-1 font-semibold text-[#397565]');
            badge.innerHTML = feedIcon(type);
            badge.appendChild(createElement('span', '', String(total)));
            badge.title = reactionNames[type] || type;
            reactions.appendChild(badge);
        }
        if (!post.reactions_count) reactions.textContent = 'No reactions yet';
        counts.append(reactions, createElement('span', '', `${post.comments_count} ${post.comments_count === 1 ? 'comment' : 'comments'}`));
        engagement.appendChild(counts);

        const actions = createElement('div', 'flex flex-wrap items-center gap-2 border-y border-[#397565]/10 py-2');
        const picker = createElement('div', 'relative');
        const react = createElement('button', `inline-flex min-h-9 items-center rounded-lg px-3 text-xs font-bold ${post.viewer_reaction ? 'bg-[#397565]/10 text-[#397565]' : 'text-[#121017]/60 hover:bg-slate-100'}`, reactionNames[post.viewer_reaction] || 'React');
        react.type = 'button';
        react.setAttribute('aria-haspopup', 'true');
        react.setAttribute('aria-expanded', 'false');
        react.setAttribute('aria-label', post.viewer_reaction ? `${reactionNames[post.viewer_reaction]} reaction. Choose another reaction.` : 'React to this post');
        const choices = createElement('div', 'absolute bottom-full left-0 z-20 hidden gap-1 rounded-lg border border-slate-200 bg-white p-2 shadow-xl');
        const showChoices = (visible) => {
            choices.classList.toggle('hidden', !visible);
            choices.classList.toggle('flex', visible);
            react.setAttribute('aria-expanded', String(visible));
        };
        picker.addEventListener('mouseenter', () => {
            if (window.matchMedia('(hover: hover)').matches) showChoices(true);
        });
        picker.addEventListener('mouseleave', () => showChoices(false));
        picker.addEventListener('focusin', () => {
            if (window.matchMedia('(hover: hover)').matches) showChoices(true);
        });
        picker.addEventListener('focusout', (event) => {
            if (!picker.contains(event.relatedTarget)) showChoices(false);
        });
        picker.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                showChoices(false);
                react.focus();
            }
        });
        react.onclick = () => {
            if (!window.matchMedia('(hover: hover)').matches) {
                showChoices(choices.classList.contains('hidden'));
                return;
            }
            feedAction({ action: 'reaction_toggle', post_id: post.id, type: 'like' }, 'Could not update your reaction.');
        };
        Object.entries(reactionNames).forEach(([type, label]) => {
            const button = createElement('button', `grid min-h-10 min-w-12 place-items-center gap-0.5 rounded-lg px-2 text-[10px] font-bold ${post.viewer_reaction === type ? 'bg-[#397565]/10 text-[#397565]' : 'text-[#121017]/65 hover:bg-slate-100'}`);
            button.type = 'button';
            button.innerHTML = feedIcon(type);
            button.appendChild(createElement('span', '', label));
            button.setAttribute('aria-label', label);
            button.setAttribute('aria-pressed', String(post.viewer_reaction === type));
            button.onclick = () => feedAction({ action: 'reaction_toggle', post_id: post.id, type }, 'Could not update your reaction.');
            choices.appendChild(button);
        });
        picker.append(react, choices);
        actions.appendChild(picker);
        const commentButton = createElement('button', 'inline-flex min-h-9 items-center gap-2 rounded-lg px-3 text-xs font-bold text-[#121017]/60 hover:bg-slate-100', 'Comment');
        commentButton.type = 'button';
        commentButton.insertAdjacentHTML('afterbegin', feedIcon('comment'));
        actions.appendChild(commentButton);
        engagement.appendChild(actions);

        const comments = createElement('div', 'grid gap-3 pt-4');
        (post.comments || []).forEach(comment => {
            const item = createElement('div', 'flex gap-2');
            item.appendChild(createElement('span', 'grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#397565]/10 text-[10px] font-black text-[#397565]', comment.author_initials));
            const content = createElement('div', 'min-w-0 flex-1');
            const bubble = createElement('div', 'rounded-lg bg-[#F7F4ED] px-3 py-2');
            const name = createElement('div', 'flex flex-wrap items-center gap-2');
            name.appendChild(createElement('strong', 'text-xs', comment.author_name));
            if (comment.user_id === post.user_id) name.appendChild(createElement('span', 'rounded bg-[#C6F24E]/45 px-1.5 py-0.5 text-[9px] font-black text-[#397565]', 'Author'));
            if (comment.is_pinned) { const pin = createElement('span', 'inline-flex items-center gap-1 text-[10px] font-bold text-[#397565]', 'Pinned'); pin.insertAdjacentHTML('afterbegin', feedIcon('pin')); name.appendChild(pin); }
            bubble.append(name, createElement('p', 'mt-1 whitespace-pre-wrap break-words text-xs leading-5', comment.body));
            content.appendChild(bubble);
            const tools = createElement('div', 'mt-1 flex flex-wrap items-center gap-3 px-2 text-[10px] font-semibold text-[#121017]/50');
            tools.appendChild(createElement('span', '', timeAgo(comment.created_at)));
            if (comment.user_id === state.userId) {
                const edit = createElement('button', 'hover:text-[#397565]', 'Edit'); edit.type = 'button';
                const remove = createElement('button', 'hover:text-red-600', 'Delete'); remove.type = 'button';
                edit.onclick = () => { commentForm.elements.body.value = comment.body; commentForm.dataset.commentId = comment.id; submit.textContent = 'Save comment'; cancel.classList.remove('hidden'); commentForm.elements.body.focus(); };
                remove.onclick = async () => { if (confirm('Delete this comment?')) await feedAction({ action: 'comment_delete', post_id: post.id, comment_id: comment.id }, 'Could not delete your comment.'); };
                tools.append(edit, remove);
                if (post.user_id === state.userId) {
                    const pin = createElement('button', 'hover:text-[#397565]', comment.is_pinned ? 'Unpin' : 'Pin'); pin.type = 'button';
                    pin.onclick = () => feedAction({ action: 'comment_pin', post_id: post.id, comment_id: comment.id, pin: !comment.is_pinned }, 'Could not change the pinned comment.');
                    tools.appendChild(pin);
                }
            }
            content.appendChild(tools);
            item.appendChild(content);
            comments.appendChild(item);
        });
        engagement.appendChild(comments);
        const commentForm = createElement('form', 'mt-4 flex items-start gap-2');
        commentForm.dataset.axiosForm = '';
        const input = createElement('textarea', 'min-h-10 min-w-0 flex-1 resize-y rounded-lg border border-[#121017]/15 bg-white px-3 py-2 text-xs outline-none focus:border-[#397565]');
        input.name = 'body'; input.rows = 1; input.maxLength = 1000; input.required = true; input.placeholder = 'Write a comment…';
        const submit = createElement('button', 'min-h-10 rounded-lg bg-[#397565] px-3 text-xs font-bold text-white', 'Post'); submit.type = 'submit';
        const cancel = createElement('button', 'hidden min-h-10 rounded-lg border px-3 text-xs font-bold', 'Cancel'); cancel.type = 'button';
        cancel.onclick = () => { commentForm.reset(); delete commentForm.dataset.commentId; submit.textContent = 'Post'; cancel.classList.add('hidden'); };
        commentForm.append(input, submit, cancel);
        commentForm.onsubmit = async event => {
            event.preventDefault();
            if (!commentForm.reportValidity()) return;
            const commentId = Number(commentForm.dataset.commentId || 0);
            submit.disabled = true;
            await feedAction({ action: commentId ? 'comment_update' : 'comment_create', post_id: post.id, comment_id: commentId, body: input.value.trim() }, 'Could not save your comment.');
            submit.disabled = false;
        };
        commentButton.onclick = () => input.focus();
        engagement.appendChild(commentForm);
        article.appendChild(engagement);
        return article;
    };

    const renderPosts = posts => {
        if (!posts.length) return;
        select('[data-feed-posts]').replaceChildren(...posts.map(feedPost));
    };

    const loadPage = async () => {
        const response = await axios.get(API_URL);
        const data = response.data.data;
        state.userId = Number(data.student.id);
        renderStudent(data.student);
        renderSubmissions(data.submissions);
        renderCurrentEvent(data.current_event);
        renderFeaturedEvents(data.featured_events);
        renderPosts(data.posts);
    };

    const initializePostForm = () => {
        const form = select('[data-post-form]');
        const button = select('[data-submit-post]');
        if (!form || !button) return;

        form.addEventListener('submit', async event => {
            event.preventDefault();
            button.disabled = true;
            button.textContent = 'Submitting…';
            try {
                const response = await axios.post(API_URL, new FormData(form), {
                    headers: { 'X-CSRF-Token': state.csrfToken },
                });
                form.reset();
                clearMedia();
                showToast(response.data.message);
                await loadPage();
            } catch (error) {
                showToast(error.response?.data?.message || 'The post could not be submitted.', 'error');
            } finally {
                button.disabled = false;
                button.textContent = 'Submit post';
            }
        });
    };

    const initializeLogout = () => {
        select('[data-logout]')?.addEventListener('click', async () => {
            try {
                await axios.post(LOGOUT_URL, {}, {
                    headers: { 'X-CSRF-Token': state.csrfToken },
                });
            } finally {
                window.location.href = './';
            }
        });
    };

    const initialize = async () => {
        initializeSidebar();
        initializeMediaPicker();
        initializePostForm();
        initializeLogout();

        try {
            const response = await axios.get(SESSION_URL);
            if (!response.data.authenticated || response.data.user?.role !== 'Student') {
                window.location.href = './';
                return;
            }
            state.csrfToken = response.data.csrf_token;
            select('meta[name="csrf-token"]').content = state.csrfToken;
            await loadPage();
        } catch {
            window.location.href = './';
        }
    };

    initialize();
})();
