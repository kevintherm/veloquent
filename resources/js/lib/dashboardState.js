import { ref } from "vue";

const activeCollection = ref({
  id: null,
  name: null,
  fields: [],
});
const recordsReloadNonce = ref(0);

const requestRecordsReload = () => {
  recordsReloadNonce.value += 1;
};

export const useDashboardState = () => {
  return {
    activeCollection,
    recordsReloadNonce,
    requestRecordsReload,
  };
};
