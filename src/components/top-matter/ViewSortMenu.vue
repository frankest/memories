<template>
  <NcActions :title="t('memories', 'Sorting')" :forceMenu="true">
    <template #icon>
      <SortDateDIcon v-if="effective === 'date'" :size="20" />
      <SortDateAIcon v-else-if="effective === 'date-asc'" :size="20" />
      <SlotAlphabeticalAIcon v-else-if="effective === 'name'" :size="20" />
      <SlotAlphabeticalDIcon v-else-if="effective === 'name-desc'" :size="20" />
      <SortIcon v-else :size="20" />
    </template>

    <NcActionRadio
      v-for="option of options"
      :key="option.value"
      name="view-sort"
      :aria-label="option.label"
      :model-value="effective"
      :value="option.value"
      @change="select(option.value)"
      close-after-click
    >
      {{ option.label }}
    </NcActionRadio>

    <template v-if="manual">
      <NcActionSeparator />
      <NcActionRadio
        name="view-sort"
        :aria-label="t('memories', 'Manual order')"
        :model-value="effective"
        value="manual"
        @change="selectManual()"
        close-after-click
      >
        {{ t('memories', 'Manual order') }}
      </NcActionRadio>
    </template>
  </NcActions>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue';

import UserConfig from '@mixins/UserConfig';

import NcActions from '@nextcloud/vue/components/NcActions';
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio';
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator';

import SortIcon from 'vue-material-design-icons/SortVariant.vue';
import SlotAlphabeticalAIcon from 'vue-material-design-icons/SortAlphabeticalAscending.vue';
import SlotAlphabeticalDIcon from 'vue-material-design-icons/SortAlphabeticalDescending.vue';
import SortDateAIcon from 'vue-material-design-icons/SortCalendarAscending.vue';
import SortDateDIcon from 'vue-material-design-icons/SortCalendarDescending.vue';

import * as utils from '@services/utils';

import type { SortOrder, SortOrderSetting } from '@typings';

/** Settings usable with this menu, and their server-side keys */
const SETTINGS = {
  sort_folder_order: { remote: 'sortFolderOrder', month: 'sort_folder_month' },
  sort_album_order: { remote: 'sortAlbumOrder', month: 'sort_album_month' },
} as const;

export default defineComponent({
  name: 'ViewSortMenu',

  components: {
    NcActions,
    NcActionRadio,
    NcActionSeparator,

    SortIcon,
    SlotAlphabeticalAIcon,
    SlotAlphabeticalDIcon,
    SortDateAIcon,
    SortDateDIcon,
  },

  mixins: [UserConfig],

  props: {
    /** Setting key to change */
    setting: {
      type: String as PropType<keyof typeof SETTINGS>,
      required: true,
    },
    /** Show the manual order option (albums only) */
    manual: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    stored(): string {
      return (this.config[this.setting] as string) || '';
    },

    /** Order currently in effect, considering the view default */
    effective(): string {
      if (this.stored) return this.stored;
      return this.config[SETTINGS[this.setting].month] ? 'date-asc' : 'date';
    },

    options(): { value: SortOrder; label: string }[] {
      return [
        { value: 'date', label: this.t('memories', 'Newest first') },
        { value: 'date-asc', label: this.t('memories', 'Oldest first') },
        { value: 'name', label: this.t('memories', 'Name (A-Z)') },
        { value: 'name-desc', label: this.t('memories', 'Name (Z-A)') },
      ];
    },
  },

  methods: {
    /** Store and persist the sort order of this view */
    async store(value: SortOrderSetting | 'manual') {
      (this.config as unknown as Record<string, string>)[this.setting] = value;
      await this.updateSetting(this.setting, SETTINGS[this.setting].remote);
    },

    async select(value: SortOrder) {
      if (this.stored === value) return;
      await this.store(value);
      this.refreshTimeline();
    },

    /**
     * The manual order is applied by the timeline, which also
     * switches the sort order of this view once it is active.
     */
    selectManual() {
      utils.bus.emit('memories:album-order:enable', null);
    },

    refreshTimeline() {
      utils.bus.emit('memories:timeline:hard-refresh', null);
    },
  },
});
</script>
