<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    {{-- Tinta selalu gelap di atas kertas terang, supaya PNG-nya terbaca di BAST dalam mode gelap sekalipun. --}}
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            context: null,
            last: null,
            init() {
                this.context = this.$refs.canvas.getContext('2d')
                new ResizeObserver(() => this.fit()).observe(this.$refs.canvas)
                this.$watch('state', (value) => value || this.erase())
            },
            fit() {
                const canvas = this.$refs.canvas
                const ratio = window.devicePixelRatio || 1
                const width = Math.round(canvas.clientWidth * ratio)
                const height = Math.round(canvas.clientHeight * ratio)

                if (! width || (canvas.width === width && canvas.height === height)) {
                    return
                }

                canvas.width = width
                canvas.height = height
                Object.assign(this.context, { lineWidth: 2.2, lineCap: 'round', lineJoin: 'round', strokeStyle: '#26261f' })
                this.context.scale(ratio, ratio)
                this.state = null
            },
            point(event) {
                const box = this.$refs.canvas.getBoundingClientRect()

                return [event.clientX - box.left, event.clientY - box.top]
            },
            start(event) {
                event.target.setPointerCapture(event.pointerId)
                this.last = this.point(event)
                this.draw(event)
            },
            draw(event) {
                if (! this.last) {
                    return
                }

                const next = this.point(event)
                this.context.beginPath()
                this.context.moveTo(...this.last)
                this.context.lineTo(...next)
                this.context.stroke()
                this.last = next
            },
            end() {
                if (this.last) {
                    this.last = null
                    this.state = this.$refs.canvas.toDataURL('image/png')
                }
            },
            erase() {
                this.context.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height)
            },
        }"
        {{ $getExtraAttributeBag()->class(['grid gap-2']) }}
    >
        <div
            wire:ignore
            class="relative h-44 overflow-hidden rounded-[var(--kertas-radius-field)] bg-gray-100 dark:bg-gray-200"
        >
            <canvas
                x-ref="canvas"
                role="img"
                aria-label="{{ $getLabel() }}"
                class="size-full cursor-crosshair touch-none"
                x-on:pointerdown="start($event)"
                x-on:pointermove="draw($event)"
                x-on:pointerup="end()"
                x-on:pointercancel="end()"
            ></canvas>

            <div class="pointer-events-none absolute inset-x-6 bottom-9 border-b border-gray-400/70 pb-1 text-lg leading-none text-gray-500">×</div>
        </div>

        <div class="flex justify-end">
            <x-filament::link tag="button" type="button" color="gray" size="sm" x-on:click="state = null">
                {{ __('Hapus tanda tangan') }}
            </x-filament::link>
        </div>
    </div>
</x-dynamic-component>
