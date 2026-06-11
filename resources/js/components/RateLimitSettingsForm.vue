<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { toast } from "vue-sonner";
import { Plus, Trash2 } from "lucide-vue-next";
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
  Input,
  Label,
  Button,
  Checkbox,
  Separator
} from "@/components/ui";

const loading = ref(false);
const saving = ref(false);
const errors = ref({});

const form = ref({
  rate_limit: {
    rate_limit_enabled: true,
    rate_limit_rules: []
  }
});

const loadSettings = async () => {
  loading.value = true;
  errors.value = {};

  try {
    const { data } = await axios.get("settings");
    if (data?.data) {
      form.value.rate_limit = {
        rate_limit_enabled: data.data.rate_limit?.rate_limit_enabled ?? true,
        rate_limit_rules: data.data.rate_limit?.rate_limit_rules || []
      };
    }
  } catch (error) {
    toast.error("Failed to load rate limit settings.");
  } finally {
    loading.value = false;
  }
};

const saveSettings = async () => {
  saving.value = true;
  errors.value = {};

  try {
    const { data } = await axios.patch("settings", form.value);
    if (data?.data) {
      form.value.rate_limit = {
        rate_limit_enabled: data.data.rate_limit?.rate_limit_enabled ?? true,
        rate_limit_rules: data.data.rate_limit?.rate_limit_rules || []
      };
    }
    toast.success("Rate limit configuration saved successfully!");
  } catch (error) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    } else {
      toast.error(error.response?.data?.message || "Failed to save rate limit configuration.");
    }
  } finally {
    saving.value = false;
  }
};

const addRule = () => {
  form.value.rate_limit.rate_limit_rules.push({
    label: "*",
    max_attempts: 100,
    decay_minutes: 1,
    audience: "all"
  });
};

const removeRule = (index) => {
  form.value.rate_limit.rate_limit_rules.splice(index, 1);
};

onMounted(() => {
  loadSettings();
});
</script>

<template>
  <div v-if="loading" class="flex justify-center p-8">
    <p class="text-muted-foreground animate-pulse">Loading rate limit settings...</p>
  </div>

  <div v-else class="space-y-6">
    <Card>
      <CardHeader>
        <CardTitle>Rate Limiting Settings</CardTitle>
        <CardDescription>
          Configure rule-based rate limiting for incoming API requests to protect resources from abuse.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-6">
        <label class="flex items-start gap-3 text-sm cursor-pointer py-2">
          <Checkbox 
            :checked="form.rate_limit.rate_limit_enabled"
            @update:checked="form.rate_limit.rate_limit_enabled = !form.rate_limit.rate_limit_enabled" 
            class="mt-0.5"
          />
          <div class="space-y-1">
            <span class="font-medium text-foreground">Enable Rate Limiting</span>
            <p class="text-xs text-muted-foreground">
              Globally toggle rate limiting on or off. When disabled, all endpoints bypass throttling.
            </p>
          </div>
        </label>

        <p v-if="errors['rate_limit.rate_limit_enabled']" class="text-xs text-red-500">
          {{ errors['rate_limit.rate_limit_enabled'][0] }}
        </p>

        <div v-if="form.rate_limit.rate_limit_enabled" class="space-y-4 pt-4 border-t">
          <div class="flex justify-between items-center">
            <div>
              <h4 class="text-sm font-semibold">Throttling Rules</h4>
              <p class="text-xs text-muted-foreground">
                Rules are evaluated in order. Match specific paths, wildcards, or tags.
              </p>
            </div>
            <Button size="sm" variant="outline" class="gap-1" @click="addRule">
              <Plus class="h-3.5 w-3.5" />
              Add Rule
            </Button>
          </div>

          <div v-if="form.rate_limit.rate_limit_rules.length === 0" class="text-center py-6 border border-dashed rounded-md bg-muted/40">
            <p class="text-xs text-muted-foreground">No rate limiting rules defined. Add one to restrict access.</p>
          </div>

          <div v-else class="space-y-3">
            <div 
              v-for="(rule, index) in form.rate_limit.rate_limit_rules" 
              :key="index" 
              class="relative p-4 rounded-lg border bg-card text-card-foreground flex flex-col md:flex-row gap-4 items-start md:items-end justify-between transition-all hover:shadow-sm"
            >
              <div class="grid gap-3 grid-cols-1 sm:grid-cols-2 md:grid-cols-4 flex-1 w-full">
                <!-- Label/Pattern -->
                <div class="space-y-2">
                  <Label :for="'label-' + index" class="text-xs">Route Pattern / Tag</Label>
                  <Input 
                    :id="'label-' + index"
                    v-model="rule.label" 
                    list="rate-limit-patterns"
                    placeholder="e.g. *, *:auth, /api/*" 
                    class="h-9 text-xs"
                  />
                  <p v-if="errors[`rate_limit.rate_limit_rules.${index}.label`]" class="text-[10px] text-red-500 mt-1">
                    {{ errors[`rate_limit.rate_limit_rules.${index}.label`][0] }}
                  </p>
                </div>

                <!-- Audience Filter -->
                <div class="space-y-2">
                  <Label :for="'audience-' + index" class="text-xs">Target Audience</Label>
                  <select 
                    :id="'audience-' + index"
                    v-model="rule.audience"
                    class="flex h-9 w-full items-center rounded-md border border-input bg-background px-3 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                  >
                    <option value="all">All Users</option>
                    <option value="guest">Guests Only</option>
                    <option value="auth">Authenticated Only</option>
                  </select>
                  <p v-if="errors[`rate_limit.rate_limit_rules.${index}.audience`]" class="text-[10px] text-red-500 mt-1">
                    {{ errors[`rate_limit.rate_limit_rules.${index}.audience`][0] }}
                  </p>
                </div>

                <!-- Max Attempts -->
                <div class="space-y-2">
                  <Label :for="'max-attempts-' + index" class="text-xs">Max Requests</Label>
                  <Input 
                    :id="'max-attempts-' + index"
                    v-model.number="rule.max_attempts" 
                    type="number"
                    min="1"
                    placeholder="240" 
                    class="h-9 text-xs"
                  />
                  <p v-if="errors[`rate_limit.rate_limit_rules.${index}.max_attempts`]" class="text-[10px] text-red-500 mt-1">
                    {{ errors[`rate_limit.rate_limit_rules.${index}.max_attempts`][0] }}
                  </p>
                </div>

                <!-- Decay Minutes -->
                <div class="space-y-2">
                  <Label :for="'decay-minutes-' + index" class="text-xs">Duration (Minutes)</Label>
                  <Input 
                    :id="'decay-minutes-' + index"
                    v-model.number="rule.decay_minutes" 
                    type="number"
                    min="1"
                    placeholder="1" 
                    class="h-9 text-xs"
                  />
                  <p v-if="errors[`rate_limit.rate_limit_rules.${index}.decay_minutes`]" class="text-[10px] text-red-500 mt-1">
                    {{ errors[`rate_limit.rate_limit_rules.${index}.decay_minutes`][0] }}
                  </p>
                </div>
              </div>

              <!-- Delete Button -->
              <Button 
                variant="ghost" 
                size="icon" 
                class="text-destructive hover:text-destructive hover:bg-destructive/10 h-9 w-9 mt-4 md:mt-0"
                @click="removeRule(index)"
              >
                <Trash2 class="h-4 w-4" />
                <span class="sr-only">Remove Rule</span>
              </Button>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <div class="flex justify-end">
      <Button :disabled="saving" @click="saveSettings">
        {{ saving ? "Saving..." : "Save Configuration" }}
      </Button>
    </div>

    <!-- Autocomplete Patterns -->
    <datalist id="rate-limit-patterns">
      <option value="*" />
      <option value="*:create" />
      <option value="*:update" />
      <option value="*:delete" />
      <option value="*:view" />
      <option value="*:list" />
      <option value="*:otp" />
      <option value="*:auth" />
      <option value="/api/collections/*" />
    </datalist>
  </div>
</template>
