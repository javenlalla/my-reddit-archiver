<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\FlairText;
use App\Entity\Post;
use App\Entity\Subreddit;
use App\Entity\Tag;
use App\Repository\FlairTextRepository;
use App\Repository\PostRepository;
use App\Repository\SubredditRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SearchFormV2 extends AbstractType
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
                ],
            ])
            ->add('subreddits', EntityType::class, [
                'class' => Subreddit::class,
                'choice_label' => 'name',
                'choice_value' => 'name',
                'placeholder' => 'Filter By Sub-Reddits',
                'query_builder' => function (SubredditRepository $repository) {
                    return $repository->createQueryBuilder('s')
                        ->orderBy('s.name', 'ASC');
                },
                'attr' => [
                    'data-model' => 'subreddits',
                ],
                'required' => false,
                'autocomplete' => true,
                'multiple' => true,
                'tom_select_options' => [
                    'placeholder' => 'Filter By Sub-Reddits',
                ],
            ])
            ->add('flairTexts', EntityType::class, [
                'class' => FlairText::class,
                'placeholder' => 'Filter By Flairs',
                'choice_label' => 'displayText',
                'choice_value' => 'displayText',
                'query_builder' => function (FlairTextRepository $repository) {
                    return $repository->createQueryBuilder('f')
                        ->orderBy('f.displayText', 'ASC');
                },
                'attr' => [
                    'data-model' => 'flairTexts',
                ],
                'required' => false,
                'autocomplete' => true,
                'multiple' => true,
                'tom_select_options' => [
                    'placeholder' => 'Filter By Flairs',
                ],
            ])
            ->add('tags', EntityType::class, [
                'placeholder' => 'Filter By Tags',
                'class' => Tag::class,
                'choice_label' => 'name',
                'choice_value' => 'name',
                'attr' => [
                    'data-model' => 'tags',
                ],
                'required' => false,
                'autocomplete' => true,
                'multiple' => true,
                'tom_select_options' => [
                    'placeholder' => 'Filter By Tags',
                    'create' => true,
                    'createOnBlur' => true,
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Search',
            ])
            ->setMethod('GET')
            ->setAction('/v2/search')
        ;
    }

    public function getBlockPrefix(): string {
        return '';
    }
}
