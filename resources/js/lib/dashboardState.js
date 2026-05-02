import { ref } from "vue";

const activeCollection = ref({
  id: null,
  name: null,
  fields: [],
});
const collections = ref([]);
const recordsReloadNonce = ref(0);
const collectionsReloadNonce = ref(0);

const requestRecordsReload = () => {
  recordsReloadNonce.value += 1;
};

const requestCollectionsReload = () => {
  collectionsReloadNonce.value += 1;
};

export const useDashboardState = () => {
  return {
    activeCollection,
    collections,
    recordsReloadNonce,
    requestRecordsReload,
    collectionsReloadNonce,
    requestCollectionsReload,
  };
};
