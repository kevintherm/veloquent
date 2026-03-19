import { ref } from "vue";

const activeCollection = ref({
  id: null,
  name: null,
  fields: [],
});

export const useDashboardState = () => {
  return {
    activeCollection,
  };
};
