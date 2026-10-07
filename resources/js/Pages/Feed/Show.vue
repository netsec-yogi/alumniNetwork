<script setup lang="ts">
import { ask } from '@/lib/confirm';
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import AppButton from '@/Components/AppButton.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import PostCard, { type FeedPost } from '@/Components/PostCard.vue';
import ReportButton from '@/Components/ReportButton.vue';
import LinkifiedText from '@/Components/LinkifiedText.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, router, useForm } from '@inertiajs/vue3';

interface Comment {
    id: number;
    body: string;
    author: { name: string; profile_id: number | null };
    at: string;
    can_delete: boolean;
}

const props = defineProps<{ post: FeedPost; comments: Paginated<Comment>; reportReasons: Record<string, string> }>();

const form = useForm({ body: '' });
const submit = () => form.post(route('posts.comments.store', props.post.id), { preserveScroll: true, onSuccess: () => form.reset() });
const remove = (c: Comment) => ask('Delete this comment?').then((ok) => ok && router.delete(route('comments.destroy', c.id), { preserveScroll: true }));
</script>

<template>
    <AppLayout title="Post">
        <AutoBreadcrumbs title="Post" class="mb-4" />
        <div class="mx-auto max-w-2xl space-y-4">
            <AppButton variant="ghost" class="-ml-3" :href="route('feed')">← Feed</AppButton>
            <PostCard :post="post" :report-reasons="reportReasons" full />

            <section class="card p-5" aria-label="Comments">
                <h2 class="text-sm font-semibold text-ink">{{ comments.total }} {{ comments.total === 1 ? 'comment' : 'comments' }}</h2>
                <ul class="mt-3 divide-y divide-line-soft">
                    <li v-for="c in comments.data" :key="c.id" class="py-3 text-sm">
                        <div class="flex items-baseline justify-between gap-2">
                            <Link v-if="c.author.profile_id" :href="route('alumni.show', c.author.profile_id)" class="font-medium text-ink hover:underline">{{ c.author.name }}</Link>
                            <span v-else class="font-medium text-ink">{{ c.author.name }}</span>
                            <span class="flex shrink-0 gap-3 text-xs text-subtle">
                                {{ c.at }}
                                <button v-if="c.can_delete" type="button" class="hover:text-red-700" @click="remove(c)">Delete</button>
                                <ReportButton type="post_comment" :id="c.id" :reasons="reportReasons" />
                            </span>
                        </div>
                        <p class="mt-1 text-ink-soft"><LinkifiedText :text="c.body" /></p>
                    </li>
                </ul>
                <PaginationNav :links="comments.links" :from="comments.from" :to="comments.to" :total="comments.total" />

                <form v-if="post.can.interact" class="mt-4 flex gap-2" @submit.prevent="submit">
                    <label for="comment" class="sr-only">Write a comment</label>
                    <textarea
                        id="comment"
                        v-model="form.body"
                        rows="2"
                        maxlength="2000"
                        placeholder="Write a comment…"
                        class="block flex-1 rounded-lg border-0 text-sm ring-1 ring-line-strong ring-inset focus:ring-2 focus:ring-brand-600"
                    />
                    <AppButton type="submit" size="sm" :loading="form.processing" :disabled="!form.body.trim()">Reply</AppButton>
                </form>
                <p v-if="form.errors.body" class="mt-1 text-sm text-red-600">{{ form.errors.body }}</p>
            </section>
        </div>
    </AppLayout>
</template>
