<?php

namespace SenseiTarzan\Jobs\Utils;

use pocketmine\lang\Translatable;
use pocketmine\player\Player;
use SenseiTarzan\Jobs\Class\Job\Award;

class CustomKnownTranslationFactory
{
    public static function level_up_job_message(string $job, int $level): Translatable
    {
        return new Translatable(CustomKnownTranslationKeys::LEVEL_UP_JOB_MESSAGE, ["job" => $job, "lvl" => $level]);
    }

    public static function add_xp_job_message(string $job, float $xp): Translatable
    {
        return new Translatable(CustomKnownTranslationKeys::ADD_XP_JOB_MESSAGE, ["job" => $job, "xp" => $xp]);
    }

    public static function award_job_message(string $award): Translatable
    {
        return new Translatable(CustomKnownTranslationKeys::AWARD_JOB_MESSAGE, ['name' => $award]);
    }

    public static function principal_button(string $job, string $xp_bar): Translatable
    {
        return new Translatable(CustomKnownTranslationKeys::PRINCIPAL_BUTTON, ['job' => $job, "xp_bar" => $xp_bar]);
    }

    public static function level_button(int $level): Translatable
    {
        return new Translatable(CustomKnownTranslationKeys::LEVEL_BUTTON, ['lvl' => $level]);
    }

    public static function xp_bar_button(float $xp, float $xp_next): Translatable
    {
        return new Translatable(CustomKnownTranslationKeys::XP_BAR_BUTTON, ['xp' => $xp, 'xp_next' => $xp_next]);
    }


    public static function description_button(): Translatable
    {
        return new Translatable(CustomKnownTranslationKeys::DESCRIPTION_BUTTON, []);
    }


    public static function awards_button(): Translatable
    {
        return new Translatable(CustomKnownTranslationKeys::AWARDS_BUTTON, []);
    }

    public static function title_job(): Translatable
    {
        return new Translatable(CustomKnownTranslationKeys::JOB_TITLE, []);
    }

    public static function name_job(string $id): Translatable
    {
        return new Translatable("Job.$id.name", []);
    }

    public static function description_job(string $id): Translatable
    {
        return new Translatable("Job.$id.description", []);
    }
    public static function name_award(string $id): Translatable
    {
        return new Translatable("Job.awards.$id.name", []);
    }
    public static function description_award(string $id): Translatable
    {
        return new Translatable("Job.awards.$id.description", []);
    }

	public function error_get_award(Player $player, Award $award, Translatable $translatable): Translatable
	{
		return new Translatable("Job.awards.errors.get", ["name" => $award->getName($player), "errno" => $translatable]);
	}

	public static function error_inventory_full(): Translatable
	{
		return new Translatable("player.error.inventory", []);
	}

	public static function error_double_request(): Translatable
	{
		return new Translatable("player.error.double_request", []);
	}
}