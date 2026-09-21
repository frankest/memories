<template>
  <router-link
    draggable="false"
    class="folder fill-block"
    :class="{
      [`folder--${sanitizedName}`]: true,
      'drop-target': dropActive,
    }"
    :to="target"
    @dragenter="dragEnter"
    @dragover="dragOver"
    @dragleave="dragLeave"
    @drop="drop"
  >
    <div class="big-icon top-left fill-block">
      <FolderIcon class="icon" />
      <div class="name">{{ data.name }}</div>
    </div>

    <div class="previews fill-block">
      <div class="preview-container fill-block">
        <div class="img-outer" v-for="info of previews" :key="info.fileid">
          <XImg class="ximg fill-block" :src="previewUrl(info)" />
        </div>
      </div>
    </div>
  </router-link>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue';

import UserConfig from '@mixins/UserConfig';

import * as utils from '@services/utils/helpers';

import type { IFolder, IPhoto } from '@typings';

import FolderIcon from 'vue-material-design-icons/Folder.vue';
import XImg from '@components/frame/XImg.vue';

export default defineComponent({
  name: 'Folder',
  components: {
    FolderIcon,
    XImg,
  },

  mixins: [UserConfig],

  props: {
    data: {
      type: Object as PropType<IFolder>,
      required: true,
    },
  },

  emits: {
    dropPhotos: (fileIds: number[]) => true,
  },

  data: () => ({
    dropActive: false,
    dropDepth: 0,
  }),

  computed: {
    /** Open folder */
    target() {
      let path: string[] | string = this.$route.params.path || [];
      if (typeof path === 'string') {
        path = path.split('/');
      }

      path = [...path, this.data.name]; // intentional copy
      return {
        name: this.$route.name,
        params: { ...this.$route.params, path },
        query: this.$route.query,
      };
    },

    previews(): IPhoto[] {
      const previews = this.data.previews;
      if (!previews?.length) {
        return [];
      }

      if (previews.length > 0 && previews.length < 4) {
        return [previews[0]];
      } else {
        return previews.slice(0, 4);
      }
    },

    sanitizedName(): string {
      return this.data.name.replaceAll(/[^a-zA-Z0-9-_]/g, '');
    },
  },

  methods: {
    /** Whether the dragged payload comes from the Memories timeline */
    isPhotoDrag(event: DragEvent): boolean {
      return !!event.dataTransfer?.types.includes('application/x-memories-photos');
    },

    dragEnter(event: DragEvent) {
      if (!this.isPhotoDrag(event)) return;
      event.preventDefault();
      this.dropDepth++;
      this.dropActive = true;
    },

    dragOver(event: DragEvent) {
      if (!this.isPhotoDrag(event)) return;
      event.preventDefault();
      if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
      this.dropActive = true;
    },

    dragLeave(event: DragEvent) {
      if (!this.isPhotoDrag(event)) return;
      this.dropDepth = Math.max(0, this.dropDepth - 1);
      if (this.dropDepth === 0) this.dropActive = false;
    },

    drop(event: DragEvent) {
      this.dropDepth = 0;
      this.dropActive = false;
      if (!this.isPhotoDrag(event)) return;
      event.preventDefault();
      try {
        const fileIds = JSON.parse(event.dataTransfer?.getData('application/x-memories-photos') ?? '[]');
        if (Array.isArray(fileIds) && fileIds.length) this.$emit('dropPhotos', fileIds);
      } catch {
        // ignore malformed payloads
      }
    },

    /** Get preview url */
    previewUrl(info: IPhoto) {
      return utils.getPreviewUrl({
        photo: info,
        sqsize: 256,
      });
    },
  },
});
</script>

<style lang="scss" scoped>
.folder {
  cursor: pointer;
}

.folder.drop-target {
  outline: 2px solid var(--color-primary);
  outline-offset: -2px;
  border-radius: 10px;
}

.big-icon {
  cursor: pointer;
  z-index: 100;
  transition: opacity 0.2s ease-in-out;

  :deep(.material-design-icon__svg) {
    width: 50%;
    height: 50%;
  }

  > .name {
    cursor: pointer;
    width: 100%;
    padding: 0 5%;
    text-align: center;
    font-size: 1.08em;
    word-wrap: break-word;
    text-overflow: ellipsis;
    max-height: 35%;
    line-height: 1em;
    position: absolute;
    top: 65%;

    @media (max-width: 768px) {
      font-size: 0.95em;
    }
  }

  // Make it white if there is a preview
  .folder:has(.previews .img-outer) > & {
    .folder-icon {
      opacity: 1;
      filter: invert(1) brightness(100);
    }
    .name {
      color: white;
    }
  }

  // Show it on hover if not a preview
  .folder:hover > & > .folder-icon {
    opacity: 0.8;
  }
  .folder:has(.previews .img-outer):hover > & {
    opacity: 0;
  }

  > .folder-icon {
    cursor: pointer;
    height: 90%;
    width: 100%;
    opacity: 0.3;
  }
}

.previews {
  z-index: 3;
  line-height: 0;
  position: absolute;
  box-sizing: border-box;

  .preview-container {
    border-radius: 10px;
    overflow: hidden;
  }

  .img-outer {
    background-color: var(--color-background-dark);
    padding: 0;
    margin: 0;
    width: 50%;
    height: 50%;
    display: inline-block;

    .folder:has(.preview-container > .img-outer:only-child) > & {
      width: 100%;
      height: 100%;
    }

    > img {
      object-fit: cover;
      padding: 0;
      filter: brightness(50%);
      transition: filter 0.2s ease-in-out;

      &.error {
        display: none;
      }
      .folder:hover & {
        filter: brightness(100%);
      }
    }
  }
}
</style>
