<?php
class FeaturePostJob extends AbstractJob
{
	protected $postRetriever;

	public function __construct()
	{
		$this->postRetriever = new PostRetriever($this);
	}

	public function execute()
	{
		$post = $this->postRetriever->retrieve();

		PropertyModel::set(PropertyModel::FeaturedPostId, $post->getId());
		PropertyModel::set(PropertyModel::FeaturedPostUnixTime, time());

		$anonymous = false;
		if ($this->hasArgument(JobArgs::ARG_ANONYMOUS))
			$anonymous = TextHelper::toBoolean($this->getArgument(JobArgs::ARG_ANONYMOUS));

		PropertyModel::set(PropertyModel::FeaturedPostUserName,
			$anonymous
			? null
			: Auth::getCurrentUser()->getName());

		Logger::log('{user} featured {post} on main page', [
			'user' => TextHelper::reprPost(PropertyModel::get(PropertyModel::FeaturedPostUserName)),
			'post' => TextHelper::reprPost($post)]);

		return $post;
	}

	public function getRequiredArguments()
	{
		return JobArgs::Conjunction(
			$this->postRetriever->getRequiredArguments(),
			JobArgs::Optional(JobArgs::ARG_ANONYMOUS));
	}

	public function getRequiredMainPrivilege()
	{
		return Privilege::FeaturePost;
	}

	public function getRequiredSubPrivileges()
	{
		return Access::getIdentity($this->postRetriever->retrieve()->getUploader());
	}

	public function isAuthenticationRequired()
	{
		return true;
	}

	public function isConfirmedEmailRequired()
	{
		return false;
	}
}
