<?php
declare(strict_types=1);

namespace App\Form;

use App\Repository\PostRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class SearchForm extends AbstractType
{
    public function __construct(private readonly PostRepository $postRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('query', SearchType::class, [
                'attr' => [
                    'placeholder' => 'Search Archive',
                    'data-search-posts-target' => 'query',
                    'data-action' => 'input->search-posts#execSearch',
                ],
            ])
            ->add('subreddits', ChoiceType::class, [
                'placeholder' => 'Sub-Reddit',
                'choices' => $this->getSubredditsChoices(),
                'attr' => [
                    'data-search-posts-target' => 'subreddit',
                    'data-action' => 'input->search-posts#execSearch',
                ]
            ])
            ->add('search', SubmitType::class)
        ;
    }

    /**
     * Retrieve the list of Subreddits to be used as choices in the Subreddit
     * Search field.
     *
     * @return array
     */
    private function getSubredditsChoices(): array
    {
        $subredditsData = $this->postRepository->findAllSubreddits();

        $subreddits = [];
        foreach ($subredditsData as $subredditData) {
            $subreddit = $subredditData['subreddit'];
            $subreddits[$subreddit] = $subreddit;
        }

        return $subreddits;
    }
}
