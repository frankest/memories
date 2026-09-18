<template>
  <section class="album-order-editor" :aria-label="t('memories', 'Edit album order')" @keydown.stop @click.stop>
    <div class="order-toolbar">
      <div class="order-title">
        <strong>{{ albumName }}</strong>
        <span>{{ t('memories', 'Drag photos to change their order. Changes apply to everyone after saving.') }}</span>
      </div>
      <div class="order-actions">
        <NcButton :disabled="saving" @click="cancel">{{ t('memories', 'Cancel') }}</NcButton>
        <NcButton v-if="manual" :disabled="saving || loading || !!error" @click="save(false)">
          {{ t('memories', 'Restore date order') }}
        </NcButton>
        <NcButton variant="primary" :disabled="saving || loading || !!error" @click="save(true)">
          {{ saving ? t('memories', 'Saving...') : t('memories', 'Save order') }}
        </NcButton>
      </div>
    </div>
    <p v-if="loading" role="status">{{ t('memories', 'Loading...') }}</p>
    <div v-if="error" class="order-error">
      <p role="alert">{{ error }}</p>
      <NcButton :disabled="saving || loading" @click="load">{{ t('memories', 'Reload album') }}</NcButton>
    </div>
    <p class="sr-only" aria-live="polite">{{ announcement }}</p>
    <ol
      ref="grid"
      class="order-grid"
      :aria-label="t('memories', 'Album order')"
      :aria-busy="loading || saving"
      @pointermove="pointerMove"
      @pointerup="pointerUp"
      @pointercancel="stopDrag"
      @lostpointercapture="stopDrag"
      @dragstart.prevent
    >
      <li
        v-for="(photo, index) in photos"
        :key="photo.fileid"
        :data-order-file="photo.fileid"
        :class="{
          dragging: dragging && draggedFile === photo.fileid,
          'target-before': dragging && targetFile === photo.fileid && !targetAfter,
          'target-after': dragging && targetFile === photo.fileid && targetAfter,
        }"
        tabindex="0"
        :aria-label="
          t('memories', '{name}, position {position}', {
            name: photo.basename || String(photo.fileid),
            position: index + 1,
          })
        "
        @pointerdown="pointerDown(photo.fileid, $event)"
        @keydown.alt.left.prevent="move(index, index - 1)"
        @keydown.alt.right.prevent="move(index, index + 1)"
        @keydown.esc.prevent="stopDrag"
        @contextmenu.prevent
      >
        <img :src="preview(photo)" :alt="photo.basename || String(photo.fileid)" loading="lazy" draggable="false" />
        <div class="tile-caption">
          <span class="filename" :title="photo.basename">{{ index + 1 }}. {{ photo.basename }}</span>
          <NcButton
            :disabled="saving || loading || index === 0"
            :aria-label="t('memories', 'Move earlier')"
            @click="move(index, index - 1)"
          >
            <template #icon><ArrowLeftIcon :size="20" /></template>
          </NcButton>
          <NcButton
            :disabled="saving || loading || index === photos.length - 1"
            :aria-label="t('memories', 'Move later')"
            @click="move(index, index + 1)"
          >
            <template #icon><ArrowRightIcon :size="20" /></template>
          </NcButton>
        </div>
      </li>
    </ol>
    <img
      v-if="dragging && draggedPhoto"
      class="drag-preview"
      :src="preview(draggedPhoto)"
      alt=""
      :style="{ left: pointerX + 16 + 'px', top: pointerY + 16 + 'px' }"
    />
  </section>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import axios from '@nextcloud/axios';
import NcButton from '@nextcloud/vue/components/NcButton';
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue';
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue';
import { API } from '@services/API';
import * as utils from '@services/utils';
import type { IPhoto } from '@typings';

export default defineComponent({
  name: 'AlbumOrderEditor',
  components: { NcButton, ArrowLeftIcon, ArrowRightIcon },
  props: {
    album: { type: String, required: true },
    albumName: { type: String, required: true },
  },
  emits: { close: (saved: boolean) => true },
  data: () => ({
    photos: [] as IPhoto[],
    revision: '',
    manual: false,
    loading: false,
    saving: false,
    error: '',
    announcement: '',
    loadId: 0,
    disposed: false,
    pointerId: null as number | null,
    draggedFile: 0,
    targetFile: 0,
    targetAfter: false,
    dragging: false,
    startX: 0,
    startY: 0,
    pointerX: 0,
    pointerY: 0,
    scrollFrame: 0,
    lastFrame: 0,
  }),
  computed: {
    draggedPhoto(): IPhoto | undefined {
      return this.photos.find((photo) => photo.fileid === this.draggedFile);
    },
  },
  mounted() {
    this.load();
  },
  beforeUnmount() {
    this.disposed = true;
    this.loadId++;
    this.stopDrag();
  },
  methods: {
    grid() {
      return this.$refs.grid as HTMLOListElement | undefined;
    },
    async load() {
      this.stopDrag();
      const id = ++this.loadId;
      this.loading = true;
      this.error = '';
      this.photos = [];
      try {
        const { data } = await axios.get<{ photos: IPhoto[]; revision: string; manual: boolean }>(
          API.ALBUM_ORDER(this.album),
        );
        if (id !== this.loadId) return;
        this.photos = data.photos;
        this.revision = data.revision;
        this.manual = data.manual;
      } catch {
        if (id === this.loadId) this.error = this.t('memories', 'Could not load album order.');
      } finally {
        if (id === this.loadId) this.loading = false;
      }
    },
    preview(photo: IPhoto) {
      return utils.getPreviewUrl({ photo, sqsize: 256 });
    },
    pointerDown(fileid: number, event: PointerEvent) {
      if (this.saving || this.loading || this.pointerId !== null || !event.isPrimary || event.button !== 0) return;
      if ((event.target as HTMLElement).closest('button')) return;
      const grid = this.grid();
      if (!grid) return;
      this.draggedFile = fileid;
      this.pointerId = event.pointerId;
      this.startX = this.pointerX = event.clientX;
      this.startY = this.pointerY = event.clientY;
      grid.setPointerCapture(event.pointerId);
    },
    pointerMove(event: PointerEvent) {
      if (event.pointerId !== this.pointerId) return;
      this.pointerX = event.clientX;
      this.pointerY = event.clientY;
      if (!this.dragging && Math.hypot(this.pointerX - this.startX, this.pointerY - this.startY) >= 6) {
        this.dragging = true;
        this.lastFrame = performance.now();
        this.scrollFrame = requestAnimationFrame(this.autoScroll);
      }
      if (this.dragging) {
        event.preventDefault();
        this.updateTarget();
      }
    },
    updateTarget() {
      const grid = this.grid();
      if (!grid) return;
      const tile = document.elementFromPoint(this.pointerX, this.pointerY)?.closest<HTMLElement>('[data-order-file]');
      if (!tile || !grid.contains(tile)) {
        this.targetFile = 0;
        return;
      }
      const fileid = Number(tile.dataset.orderFile);
      if (fileid === this.draggedFile) {
        this.targetFile = 0;
        return;
      }
      const rect = tile.getBoundingClientRect();
      const rtl = getComputedStyle(grid).direction === 'rtl';
      this.targetFile = fileid;
      this.targetAfter = rtl ? this.pointerX < rect.left + rect.width / 2 : this.pointerX > rect.left + rect.width / 2;
    },
    autoScroll(time: number) {
      if (!this.dragging) return;
      const grid = this.grid();
      if (!grid) {
        this.stopDrag();
        return;
      }
      const rect = grid.getBoundingClientRect();
      const edge = Math.min(64, rect.height / 4);
      const elapsed = Math.min(32, time - this.lastFrame) / 1000;
      this.lastFrame = time;
      let speed = 0;
      if (this.pointerX >= rect.left && this.pointerX <= rect.right) {
        if (this.pointerY < rect.top + edge) speed = -Math.min(1, (rect.top + edge - this.pointerY) / edge);
        else if (this.pointerY > rect.bottom - edge) speed = Math.min(1, (this.pointerY - rect.bottom + edge) / edge);
      }
      if (speed) {
        grid.scrollTop += speed * 650 * elapsed;
        this.updateTarget();
      }
      this.scrollFrame = requestAnimationFrame(this.autoScroll);
    },
    pointerUp(event: PointerEvent) {
      if (event.pointerId !== this.pointerId) return;
      if (this.dragging) {
        this.pointerX = event.clientX;
        this.pointerY = event.clientY;
        this.updateTarget();
        const from = this.photos.findIndex((photo) => photo.fileid === this.draggedFile);
        const target = this.photos.findIndex((photo) => photo.fileid === this.targetFile);
        if (from >= 0 && target >= 0) {
          const insertion = target + Number(this.targetAfter);
          this.move(from, insertion - Number(from < insertion));
        }
      }
      this.stopDrag();
    },
    stopDrag() {
      cancelAnimationFrame(this.scrollFrame);
      this.scrollFrame = 0;
      const grid = this.grid();
      const id = this.pointerId;
      this.pointerId = null;
      this.dragging = false;
      this.draggedFile = this.targetFile = 0;
      if (id !== null && grid?.hasPointerCapture(id)) grid.releasePointerCapture(id);
    },
    move(from: number, to: number) {
      if (this.saving || this.loading || from < 0 || to < 0 || to >= this.photos.length || from === to) return;
      const [photo] = this.photos.splice(from, 1);
      this.photos.splice(to, 0, photo);
      this.announcement = this.t('memories', '{name}, position {position}', {
        name: photo.basename || String(photo.fileid),
        position: to + 1,
      });
      this.$nextTick(() => {
        this.grid()
          ?.querySelector<HTMLElement>('[data-order-file="' + photo.fileid + '"]')
          ?.focus({ preventScroll: true });
      });
    },
    cancel() {
      if (this.saving) return;
      this.stopDrag();
      this.$emit('close', false);
    },
    async save(manual: boolean) {
      if (this.saving || this.loading || this.error) return;
      this.stopDrag();
      this.saving = true;
      try {
        await axios.put(API.ALBUM_ORDER(this.album), {
          fileIds: this.photos.map((photo) => photo.fileid),
          revision: this.revision,
          manual,
        });
        if (!this.disposed) this.$emit('close', true);
      } catch (error: any) {
        if (!this.disposed)
          this.error =
            error.response?.status === 409
              ? this.t('memories', 'The album changed. Reload it before saving again.')
              : this.t('memories', 'Could not save album order. Your changes have not been saved.');
      } finally {
        this.saving = false;
      }
    },
  },
});
</script>

<style lang="scss" scoped>
.album-order-editor {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}
.order-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding: 12px;
  border-bottom: 1px solid var(--color-border);
}
.order-title {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.order-title span {
  color: var(--color-text-maxcontrast);
}
.order-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.order-error {
  padding: 8px 12px;
}
.order-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 8px;
  flex: 1;
  min-height: 0;
  overflow: auto;
  align-content: start;
  padding: 8px;
  list-style: none;
  li {
    position: relative;
    border: 3px solid transparent;
    border-radius: 8px;
    cursor: grab;
    touch-action: none;
    user-select: none;
  }
  li.target-before {
    border-inline-start-color: var(--color-primary);
  }
  li.target-after {
    border-inline-end-color: var(--color-primary);
  }
  li.dragging {
    opacity: 0.4;
  }
  li:focus-visible {
    outline: 2px solid var(--color-primary);
  }
  img {
    display: block;
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: 4px;
    pointer-events: none;
  }
  .tile-caption {
    display: flex;
    align-items: center;
    gap: 2px;
  }
  .filename {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
.drag-preview {
  position: fixed;
  width: 96px;
  height: 96px;
  object-fit: cover;
  pointer-events: none;
  z-index: 10000;
  opacity: 0.85;
  border-radius: 6px;
  box-shadow: 0 2px 10px #0005;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
}
@media (max-width: 600px) {
  .order-toolbar {
    padding-top: 52px;
  }
  .order-grid {
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  }
}
</style>
