<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\MedicalGuide\Models\MedicalGuide;
use Modules\MedicalGuide\Models\MedicalGuidePlace;
use Modules\MedicalGuide\Resources\DoctorMedicalGuideResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DoctorMedicalGuideController extends Controller
{
    public function show(Request $request)
    {
        $medicalGuide = $this->currentDoctorMedicalGuide($request);

        $medicalGuide->load([
            'specialty',
            'province',
            'places',
        ]);

        return response()->json([
            'medical_guide' => DoctorMedicalGuideResource::make($medicalGuide),
        ]);
    }

    public function toggle(Request $request)
    {
        $medicalGuide = $this->currentDoctorMedicalGuide($request);

        return $this->setMedicalGuideActive($medicalGuide, ! $medicalGuide->is_active);
    }

    public function toggleClinic(Request $request, MedicalGuidePlace $clinic)
    {
        $medicalGuide = $this->currentDoctorMedicalGuide($request);

        return $this->setClinicActive($medicalGuide, $clinic, ! $clinic->is_active);
    }

    private function setMedicalGuideActive(MedicalGuide $medicalGuide, bool $active)
    {
        $medicalGuide->forceFill([
            'is_active' => $active,
        ])->save();

        $medicalGuide->load([
            'specialty',
            'province',
            'places',
        ]);

        return response()->json([
            'medical_guide' => DoctorMedicalGuideResource::make($medicalGuide),
        ]);
    }

    private function setClinicActive(MedicalGuide $medicalGuide, MedicalGuidePlace $clinic, bool $active)
    {
        if ((int) $clinic->medical_guide_id !== (int) $medicalGuide->id) {
            throw new NotFoundHttpException('Clinic not found.');
        }

        $clinic->forceFill([
            'is_active' => $active,
        ])->save();

        $medicalGuide->load([
            'specialty',
            'province',
            'places',
        ]);

        return response()->json([
            'medical_guide' => DoctorMedicalGuideResource::make($medicalGuide),
        ]);
    }

    private function currentDoctorMedicalGuide(Request $request): MedicalGuide
    {
        $regNumber = $this->normalizeRegisterNumber($request->user()?->reg_number);

        if ($regNumber === '') {
            throw new NotFoundHttpException('Medical guide not found.');
        }

        $medicalGuide = MedicalGuide::query()
            ->where('reg_number', $regNumber)
            ->first();

        if (! $medicalGuide instanceof MedicalGuide) {
            throw new NotFoundHttpException('Medical guide not found.');
        }

        return $medicalGuide;
    }

    private function normalizeRegisterNumber(mixed $value): string
    {
        $normalized = strtr(trim((string) $value), [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]);

        return preg_replace('/\D+/', '', $normalized) ?? '';
    }
}
