<?php

namespace App\Actions\Exams;

use App\Models\Exam;
use App\Models\ExamCatalogVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PublishExamCatalogVersion
{
    public function __construct(private BuildExamSnapshot $buildSnapshot) {}

    public function handle(Exam $exam, User $user): ExamCatalogVersion
    {
        Gate::forUser($user)->authorize('update', $exam);

        return DB::transaction(function () use ($exam, $user): ExamCatalogVersion {
            $exam = Exam::query()->lockForUpdate()->findOrFail($exam->getKey());

            return ExamCatalogVersion::create([
                'exam_id' => $exam->getKey(),
                'version_number' => ((int) $exam->catalogVersions()->max('version_number')) + 1,
                'snapshot' => $this->buildSnapshot->handle($exam),
                'created_by' => $user->getKey(),
                'published_at' => now(),
            ]);
        });
    }
}
