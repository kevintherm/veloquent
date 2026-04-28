<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { toast } from "vue-sonner";
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
  general: {
    app_name: "",
    app_url: "",
    timezone: "UTC",
    locale: "en",
    contact_email: "",
    lock_schema_change: false,
  },
  storage: {
    storage_driver: "local",
    backup_enabled: false,
    retention_days: 7,
    s3_key: "",
    s3_secret: "",
    s3_region: "",
    s3_bucket: "",
    s3_endpoint: ""
  },
  email: {
    mail_driver: "smtp",
    mail_host: "127.0.0.1",
    mail_port: 1025,
    mail_encryption: "tls",
    mail_username: "",
    mail_password: "",
    mail_from_address: "",
    mail_from_name: ""
  }
});

const loadSettings = async () => {
  loading.value = true;
  errors.value = {};

  try {
    const { data } = await axios.get("/api/settings");
    if (data?.data) {
      Object.assign(form.value, data.data);
    }
  } catch (error) {
    toast.error("Failed to load settings.");
  } finally {
    loading.value = false;
  }
};

const saveSettings = async () => {
  saving.value = true;
  errors.value = {};

  try {
    const { data } = await axios.patch("/api/settings", form.value);
    if (data?.data) {
      Object.assign(form.value, data.data);
    }
    toast.success("Settings updated successfully");
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  loadSettings();
});
</script>

<template>
  <div v-if="loading" class="flex justify-center p-8">
    <p class="text-muted-foreground animate-pulse">Loading settings...</p>
  </div>

  <div v-else class="space-y-6">
    <Card>
      <CardHeader>
        <CardTitle>General</CardTitle>
        <CardDescription>Configure basic application information.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="space-y-2">
          <Label>App Name</Label>
          <Input v-model="form.general.app_name" placeholder="My App" />
          <p v-if="errors['general.app_name']" class="text-xs text-red-500">{{ errors['general.app_name'][0] }}</p>
        </div>

        <div class="space-y-2">
          <Label>App URL</Label>
          <Input v-model="form.general.app_url" placeholder="https://example.com" />
          <p v-if="errors['general.app_url']" class="text-xs text-red-500">{{ errors['general.app_url'][0] }}</p>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <div class="space-y-2">
            <Label>Timezone</Label>
            <select v-model="form.general.timezone"
              class="flex h-10 w-full items-center rounded-md border border-input bg-background px-3 py-2 text-sm">
              <option value="UTC">UTC</option>
              <option value="America/New_York">Eastern Time (US & Canada)</option>
              <option value="America/Chicago">Central Time (US & Canada)</option>
              <option value="America/Denver">Mountain Time (US & Canada)</option>
              <option value="America/Los_Angeles">Pacific Time (US & Canada)</option>
              <option value="Europe/London">London</option>
              <option value="Europe/Paris">Paris</option>
              <option value="Asia/Tokyo">Tokyo</option>
              <option value="Asia/Singapore">Singapore</option>
              <option value="Asia/Jakarta">Jakarta (GMT+7)</option>
              <option value="Australia/Sydney">Sydney</option>
            </select>
            <p v-if="errors['general.timezone']" class="text-xs text-red-500">{{ errors['general.timezone'][0] }}</p>
          </div>
          <div class="space-y-2">
            <Label>Locale</Label>
            <select v-model="form.general.locale"
              class="flex h-10 w-full items-center rounded-md border border-input bg-background px-3 py-2 text-sm">
              <option value="en">English (en)</option>
            </select>
            <p v-if="errors['general.locale']" class="text-xs text-red-500">{{ errors['general.locale'][0] }}</p>
          </div>
        </div>

        <div class="space-y-2">
          <Label>Contact Email</Label>
          <Input v-model="form.general.contact_email" type="email" placeholder="admin@example.com" />
          <p v-if="errors['general.contact_email']" class="text-xs text-red-500">{{ errors['general.contact_email'][0]
          }}</p>
        </div>

        <label class="flex items-center gap-2 text-sm cursor-pointer py-2">
          <Checkbox :checked="form.general.lock_schema_change"
            @update:checked="form.general.lock_schema_change = !form.general.lock_schema_change" />
          <span>Lock Schema Changes (Prevents modifying database schema)</span>
        </label>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Storage & Backups</CardTitle>
        <CardDescription>Manage where files are stored and backup policies.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="space-y-2">
          <Label>Storage Driver</Label>
          <select v-model="form.storage.storage_driver"
            class="flex h-10 w-full items-center rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option value="local">Local Disk</option>
            <option value="s3">S3</option>
          </select>
          <p v-if="errors['storage.storage_driver']" class="text-xs text-red-500">{{ errors['storage.storage_driver'][0]
          }}</p>
        </div>

        <div v-if="form.storage.storage_driver === 's3'" class="space-y-4 pt-4 border-t">
          <div class="grid gap-3 md:grid-cols-2">
            <div class="space-y-2">
              <Label>S3 Key</Label>
              <Input v-model="form.storage.s3_key" placeholder="AKIA..." />
              <p v-if="errors['storage.s3_key']" class="text-xs text-red-500">{{ errors['storage.s3_key'][0] }}</p>
            </div>
            <div class="space-y-2">
              <Label>S3 Secret</Label>
              <Input v-model="form.storage.s3_secret" type="password" placeholder="••••••••" />
              <p v-if="errors['storage.s3_secret']" class="text-xs text-red-500">{{ errors['storage.s3_secret'][0] }}
              </p>
            </div>
          </div>
          <div class="grid gap-3 md:grid-cols-2">
            <div class="space-y-2">
              <Label>S3 Region</Label>
              <Input v-model="form.storage.s3_region" placeholder="us-east-1" />
              <p v-if="errors['storage.s3_region']" class="text-xs text-red-500">{{ errors['storage.s3_region'][0] }}
              </p>
            </div>
            <div class="space-y-2">
              <Label>S3 Bucket</Label>
              <Input v-model="form.storage.s3_bucket" placeholder="my-bucket" />
              <p v-if="errors['storage.s3_bucket']" class="text-xs text-red-500">{{ errors['storage.s3_bucket'][0] }}
              </p>
            </div>
          </div>
          <div class="space-y-2">
            <Label>S3 Endpoint (Optional)</Label>
            <Input v-model="form.storage.s3_endpoint" placeholder="https://s3.amazonaws.com" />
            <p v-if="errors['storage.s3_endpoint']" class="text-xs text-red-500">{{ errors['storage.s3_endpoint'][0] }}
            </p>
          </div>
        </div>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Email settings (SMTP)</CardTitle>
        <CardDescription>Configuration for outgoing application emails.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="grid gap-3 md:grid-cols-2">
          <div class="space-y-2">
            <Label>Mail Driver</Label>
            <select v-model="form.email.mail_driver"
              class="flex h-10 w-full items-center rounded-md border border-input bg-background px-3 py-2 text-sm">
              <option value="smtp">SMTP</option>
              <option value="sendmail">Sendmail</option>
              <option value="mailgun">Mailgun</option>
            </select>
            <p v-if="errors['email.mail_driver']" class="text-xs text-red-500">{{ errors['email.mail_driver'][0] }}</p>
          </div>
          <div class="space-y-2">
            <Label>Encryption</Label>
            <select v-model="form.email.mail_encryption"
              class="flex h-10 w-full items-center rounded-md border border-input bg-background px-3 py-2 text-sm">
              <option value="tls">TLS</option>
              <option value="ssl">SSL</option>
            </select>
            <p v-if="errors['email.mail_encryption']" class="text-xs text-red-500">{{ errors['email.mail_encryption'][0]
            }}</p>
          </div>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
          <div class="space-y-2 col-span-2">
            <Label>Host</Label>
            <Input v-model="form.email.mail_host" placeholder="smtp.example.com" />
            <p v-if="errors['email.mail_host']" class="text-xs text-red-500">{{ errors['email.mail_host'][0] }}</p>
          </div>
          <div class="space-y-2">
            <Label>Port</Label>
            <Input type="number" v-model="form.email.mail_port" placeholder="587" />
            <p v-if="errors['email.mail_port']" class="text-xs text-red-500">{{ errors['email.mail_port'][0] }}</p>
          </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <div class="space-y-2">
            <Label>Username</Label>
            <Input v-model="form.email.mail_username" />
            <p v-if="errors['email.mail_username']" class="text-xs text-red-500">{{ errors['email.mail_username'][0] }}
            </p>
          </div>
          <div class="space-y-2">
            <Label>Password</Label>
            <Input v-model="form.email.mail_password" type="password" placeholder="••••••••" />
            <p v-if="errors['email.mail_password']" class="text-xs text-red-500">{{ errors['email.mail_password'][0] }}
            </p>
          </div>
        </div>

        <Separator />

        <div class="grid gap-3 md:grid-cols-2">
          <div class="space-y-2">
            <Label>From Name</Label>
            <Input v-model="form.email.mail_from_name" placeholder="My App Support" />
            <p v-if="errors['email.mail_from_name']" class="text-xs text-red-500">{{ errors['email.mail_from_name'][0]
            }}</p>
          </div>
          <div class="space-y-2">
            <Label>From Address</Label>
            <Input v-model="form.email.mail_from_address" type="email" placeholder="noreply@example.com" />
            <p v-if="errors['email.mail_from_address']" class="text-xs text-red-500">{{
              errors['email.mail_from_address'][0] }}</p>
          </div>
        </div>
      </CardContent>
    </Card>

    <div class="flex justify-end">
      <Button :disabled="saving" @click="saveSettings">
        {{ saving ? "Saving..." : "Save Configuration" }}
      </Button>
    </div>
  </div>
</template>
