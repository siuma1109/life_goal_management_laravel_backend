<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Comment;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Create 10 users
        $users = User::factory(10)->create();

        foreach($users as $user) {
            // Create 10 projects for each user
            $projects = Project::factory()->count(10)->create([
                'user_id' => $user->id,
            ]);

            // Create 10 inbox tasks (tasks without a project)
            $inboxTasks = Task::factory()->count(10)->create([
                'user_id' => $user->id,
                'project_id' => null,
            ]);

            // Create 10 project tasks
            $projectTasks = Task::factory()->count(10)->create([
                'project_id' => $faker->randomElement($projects->pluck('id')->toArray()),
                'user_id' => $user->id,
            ]);

            // Create subtasks and comments for inbox tasks
            foreach($inboxTasks as $task) {
                // Create 3 subtasks for each inbox task
                $subtasks = Task::factory()->count(3)->create([
                    'user_id' => $user->id,
                    'parent_id' => $task->id,
                ]);

                // Create 2 comments for each inbox task
                Comment::factory()->count(2)->create([
                    'user_id' => $user->id,
                    'commentable_id' => $task->id,
                    'commentable_type' => Task::class,
                ]);
            }

            // Create subtasks and comments for project tasks
            foreach($projectTasks as $task) {
                // Create 3 subtasks for each project task
                $subtasks = Task::factory()->count(3)->create([
                    'user_id' => $user->id,
                    'parent_id' => $task->id,
                    'project_id' => $task->project_id,
                ]);

                // Create 2 comments for each project task
                Comment::factory()->count(2)->create([
                    'user_id' => $user->id,
                    'commentable_id' => $task->id,
                    'commentable_type' => Task::class,
                ]);
            }
        }
    }
}
