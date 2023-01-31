<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\Tag;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ContentTagsForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tags', EntityType::class, [
                'placeholder' => 'Select Tags',
                'class' => Tag::class,
                'choice_name' => 'name',
                'choice_label' => 'name',
                'choice_value' => 'name',
                'attr' => [
                    'data-model' => 'norender|tags',
                ],
                'required' => false,
                'autocomplete' => true,
                'multiple' => true,
                'tom_select_options' => [
                    'placeholder' => 'Select Tags',
                    'create' => true,
                    'createOnBlur' => true,
                ],
            ])
        ;
    }
}
