<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\Post;
use App\Entity\Subreddit;
use App\Entity\Tag;
use App\Repository\PostRepository;
use App\Repository\SubredditRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SearchForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('query', SearchType::class, [
                'attr' => [
                    'placeholder' => 'Search Archive',
                    'data-model' => 'debounce(200)|query',
                ],
                'required' => false,
                'constraints' => [
                    new Length(['min' => 3]),
                    new NotBlank(),
                ],
            ])
            ->add('subreddits', EntityType::class, [
                'class' => Subreddit::class,
                'choice_label' => 'name',
                'choice_value' => 'name',
                'placeholder' => 'Filter By Sub-Reddit',
                'query_builder' => function (SubredditRepository $repository) {
                    return $repository->createQueryBuilder('s')
                        ->orderBy('s.name', 'ASC');
                },
                'attr' => [
                    'data-model' => 'subreddit',
                ],
                'required' => false,
            ])
            ->add('flairTexts', EntityType::class, [
                'class' => Post::class,
                'placeholder' => 'Filter By Flair',
                'choice_label' => 'flairText',
                'choice_value' => 'flairText',
                'query_builder' => function (PostRepository $repository) {
                    return $repository->createQueryBuilder('p')
                        ->distinct()
                        ->where("p.flairText IS NOT NULL AND p.flairText != ''")
                        ->orderBy('p.flairText');
                },
                'attr' => [
                    'data-model' => 'flairText',
                ],
                'required' => false,
            ])
            ->add('tags', EntityType::class, [
                'placeholder' => 'Filter By Tag',
                'class' => Tag::class,
                'choice_label' => 'name',
                'attr' => [
                    'data-model' => 'tag',
                ],
                'required' => false,
            ])
        ;
    }
}
