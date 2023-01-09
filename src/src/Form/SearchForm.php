<?php
declare(strict_types=1);

namespace App\Form;

use App\Repository\PostRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Routing\RouterInterface;

class SearchForm extends AbstractType
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly RouterInterface $router,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('query', SearchType::class, [
                'attr' => [
                    'placeholder' => 'Search Archive',
                    'data-model' => 'query',
                    // 'data-search-posts-target' => 'query',
                    // 'data-action' => 'input->search-posts#execSearch',
                ],
                'required' => false,
            ])
            ->add('subreddits', ChoiceType::class, [
                'placeholder' => 'Filter By Sub-Reddit',
                'choices' => $this->getSubredditChoices(),
                'attr' => [
                    'data-model' => 'subreddit',
                    // 'data-search-posts-target' => 'subreddit',
                    // 'data-action' => 'input->search-posts#execSearch',
                ],
                'required' => false,
            ])
            ->add('flairTexts', ChoiceType::class, [
                'placeholder' => 'Filter By Flair',
                'choices' => $this->getFlairTextChoices(),
                'attr' => [
                    'data-model' => 'flairText',
                    // 'data-search-posts-target' => 'flairText',
                    // 'data-action' => 'input->search-posts#execSearch',
                ],
                'required' => false,
            ])
            ->setAction($this->router->generate('search-form'))
            // ->add('search', SubmitType::class)
        ;
    }

    /**
     * Retrieve the list of Subreddits to be used as choices in the Subreddit
     * Search field.
     *
     * @return array
     */
    private function getSubredditChoices(): array
    {
        $subredditsData = $this->postRepository->findAllSubreddits();

        $subreddits = [];
        foreach ($subredditsData as $subredditData) {
            $subreddit = $subredditData['subreddit'];
            $subreddits[$subreddit] = $subreddit;
        }

        return $subreddits;
    }

    /**
     * Retrieve the list of Flair Texts to be used as choices in the Flair Text
     * Search field.
     *
     * @return array
     */
    private function getFlairTextChoices(): array
    {
        $flairTextsData = $this->postRepository->findAllFlairTexts();

        $flairTexts = [];
        foreach ($flairTextsData as $flairTextData) {
            $flairText = $flairTextData['flairText'];

            if (!empty($flairText)) {
                $flairTexts[$flairText] = $flairText;
            }
        }

        return $flairTexts;
    }
}
