@props(['input'])

<div
    data-avatar-cropper="{{ $input }}"
    class="fixed inset-0 z-[60] hidden place-items-center bg-black/60 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $input }}-cropper-title"
>
    <div class="w-full max-w-sm rounded-2xl bg-card p-4 text-left text-foreground shadow-xl">
        <h3 id="{{ $input }}-cropper-title" class="font-display text-base font-bold">Adjust Photo</h3>
        <p class="mt-0.5 text-xs text-muted-foreground">Drag the photo to reposition it and use the slider to zoom.</p>

        <div data-cropper-stage class="relative mx-auto mt-3 aspect-square w-full max-w-72 cursor-grab touch-none select-none overflow-hidden rounded-xl bg-muted active:cursor-grabbing">
            <img data-cropper-image alt="" draggable="false" class="pointer-events-none absolute left-0 top-0 max-w-none origin-top-left">
            <div class="pointer-events-none absolute inset-0 rounded-full shadow-[0_0_0_9999px_rgba(0,0,0,0.55)] ring-2 ring-white/80"></div>
        </div>

        <div class="mt-4 flex items-center gap-3 text-muted-foreground">
            <x-icons.image class="h-3.5 w-3.5 shrink-0" />
            <input data-cropper-zoom type="range" min="1" max="3" step="0.01" value="1" class="h-2 w-full cursor-pointer accent-[#7d1f2a]" aria-label="Zoom">
            <x-icons.image class="h-5 w-5 shrink-0" />
        </div>

        <p data-cropper-error class="mt-3 hidden text-xs font-medium text-destructive"></p>

        <div class="mt-4 flex justify-end gap-2">
            <button type="button" data-cropper-cancel class="inline-flex h-10 items-center justify-center rounded-full border border-border px-4 text-sm font-semibold transition-colors hover:bg-accent hover:text-accent-foreground">Cancel</button>
            <button type="button" data-cropper-save class="inline-flex h-10 items-center justify-center rounded-full bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50">Save Photo</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const modal = document.querySelector('[data-avatar-cropper="{{ $input }}"]');
        const input = document.getElementById('{{ $input }}');
        if (!modal || !input) return;

        const stage = modal.querySelector('[data-cropper-stage]');
        const image = modal.querySelector('[data-cropper-image]');
        const zoom = modal.querySelector('[data-cropper-zoom]');
        const error = modal.querySelector('[data-cropper-error]');
        const saveButton = modal.querySelector('[data-cropper-save]');
        const cancelButton = modal.querySelector('[data-cropper-cancel]');
        const outputSize = 512;
        const maxZoom = Number(zoom.max);
        const pointers = new Map();
        let state = null;
        let pinch = null;
        let objectUrl = null;

        const render = () => {
            const width = image.naturalWidth * state.scale;
            const height = image.naturalHeight * state.scale;
            state.x = Math.min(0, Math.max(state.size - width, state.x));
            state.y = Math.min(0, Math.max(state.size - height, state.y));
            image.style.width = `${width}px`;
            image.style.height = `${height}px`;
            image.style.transform = `translate(${state.x}px, ${state.y}px)`;
        };

        // Zoom around the centre of the crop area so the subject stays in place.
        const setZoom = (value) => {
            if (!state) return;
            const level = Math.min(maxZoom, Math.max(1, value));
            const scale = state.base * level;
            const centerX = (state.size / 2 - state.x) / state.scale;
            const centerY = (state.size / 2 - state.y) / state.scale;
            state.scale = scale;
            state.x = state.size / 2 - centerX * scale;
            state.y = state.size / 2 - centerY * scale;
            zoom.value = level;
            render();
        };

        const showError = (message) => {
            error.textContent = message;
            error.classList.toggle('hidden', !message);
        };

        const open = () => {
            modal.classList.remove('hidden');
            modal.classList.add('grid');
        };

        const close = () => {
            modal.classList.add('hidden');
            modal.classList.remove('grid');
            image.removeAttribute('src');
            image.removeAttribute('style');
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
            state = null;
            pinch = null;
            pointers.clear();
            showError('');
        };

        const cancel = () => {
            input.value = '';
            close();
        };

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;

            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = URL.createObjectURL(file);
            saveButton.disabled = true;
            showError('');

            image.onload = () => {
                open();
                const size = stage.clientWidth;
                const base = size / Math.min(image.naturalWidth, image.naturalHeight);
                state = {
                    size,
                    base,
                    scale: base,
                    x: (size - image.naturalWidth * base) / 2,
                    y: (size - image.naturalHeight * base) / 2,
                };
                zoom.value = 1;
                zoom.disabled = false;
                saveButton.disabled = false;
                render();
            };

            image.onerror = () => {
                open();
                zoom.disabled = true;
                showError('This file could not be opened as an image. Choose a JPG, PNG, or WEBP photo.');
            };

            image.src = objectUrl;
        });

        stage.addEventListener('pointerdown', (event) => {
            if (!state) return;
            stage.setPointerCapture(event.pointerId);
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

            if (pointers.size === 2) {
                const [first, second] = [...pointers.values()];
                pinch = {
                    distance: Math.hypot(first.x - second.x, first.y - second.y) || 1,
                    level: state.scale / state.base,
                };
            }
        });

        stage.addEventListener('pointermove', (event) => {
            const previous = pointers.get(event.pointerId);
            if (!state || !previous) return;
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

            if (pointers.size === 1) {
                state.x += event.clientX - previous.x;
                state.y += event.clientY - previous.y;
                render();
            } else if (pointers.size === 2 && pinch) {
                const [first, second] = [...pointers.values()];
                setZoom(pinch.level * (Math.hypot(first.x - second.x, first.y - second.y) / pinch.distance));
            }
        });

        const releasePointer = (event) => {
            pointers.delete(event.pointerId);
            pinch = null;
        };

        stage.addEventListener('pointerup', releasePointer);
        stage.addEventListener('pointercancel', releasePointer);

        stage.addEventListener('wheel', (event) => {
            if (!state) return;
            event.preventDefault();
            setZoom((state.scale / state.base) * (event.deltaY < 0 ? 1.08 : 1 / 1.08));
        }, { passive: false });

        zoom.addEventListener('input', () => setZoom(Number(zoom.value)));

        window.addEventListener('resize', () => {
            if (!state || !stage.clientWidth || stage.clientWidth === state.size) return;
            const ratio = stage.clientWidth / state.size;
            state.size = stage.clientWidth;
            state.base *= ratio;
            state.scale *= ratio;
            state.x *= ratio;
            state.y *= ratio;
            render();
        });

        cancelButton.addEventListener('click', cancel);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) cancel();
        });

        saveButton.addEventListener('click', () => {
            if (!state) return;

            const canvas = document.createElement('canvas');
            canvas.width = outputSize;
            canvas.height = outputSize;
            const context = canvas.getContext('2d');
            const sourceSize = state.size / state.scale;
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, outputSize, outputSize);
            context.drawImage(image, -state.x / state.scale, -state.y / state.scale, sourceSize, sourceSize, 0, 0, outputSize, outputSize);

            saveButton.disabled = true;
            canvas.toBlob((blob) => {
                if (!blob) {
                    saveButton.disabled = false;
                    showError('The photo could not be prepared. Try a different image.');
                    return;
                }

                const transfer = new DataTransfer();
                transfer.items.add(new File([blob], 'avatar.jpg', { type: 'image/jpeg' }));
                input.files = transfer.files;
                input.form.submit();
            }, 'image/jpeg', 0.9);
        });
    })();
</script>
