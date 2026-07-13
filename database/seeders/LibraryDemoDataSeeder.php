<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Demo data for the Library module: ~100 books spread across all branches, plus
 * borrowing history — a mix of currently-issued, overdue, and returned (some with
 * late fines) loans against real students/teachers, tied to the current academic year.
 *
 * Idempotent for a demo: it CLEARS existing books/book_issues first, then reseeds.
 * Loan rules mirror LibraryController: Student 14 days, Teacher 30 days, fine ₹5/day.
 */
class LibraryDemoDataSeeder extends Seeder
{
    private const CATALOG = [
        ['Clean Code', 'Robert C. Martin', 'Programming', 'Prentice Hall', 2008],
        ['The Pragmatic Programmer', 'Andrew Hunt', 'Programming', 'Addison-Wesley', 1999],
        ['Introduction to Algorithms', 'Thomas H. Cormen', 'Computer Science', 'MIT Press', 2009],
        ['Refactoring', 'Martin Fowler', 'Programming', 'Addison-Wesley', 2018],
        ['A Brief History of Time', 'Stephen Hawking', 'Science', 'Bantam', 1988],
        ['The Selfish Gene', 'Richard Dawkins', 'Science', 'Oxford University Press', 1976],
        ['Cosmos', 'Carl Sagan', 'Science', 'Random House', 1980],
        ['Sapiens', 'Yuval Noah Harari', 'History', 'Harper', 2011],
        ['Guns, Germs, and Steel', 'Jared Diamond', 'History', 'W. W. Norton', 1997],
        ['The Diary of a Young Girl', 'Anne Frank', 'Biography', 'Contact Publishing', 1947],
        ['To Kill a Mockingbird', 'Harper Lee', 'Literature', 'J. B. Lippincott', 1960],
        ['1984', 'George Orwell', 'Literature', 'Secker & Warburg', 1949],
        ['Pride and Prejudice', 'Jane Austen', 'Literature', 'T. Egerton', 1813],
        ['The Great Gatsby', 'F. Scott Fitzgerald', 'Literature', 'Scribner', 1925],
        ['Wings of Fire', 'A. P. J. Abdul Kalam', 'Biography', 'Universities Press', 1999],
        ['The Alchemist', 'Paulo Coelho', 'Fiction', 'HarperTorch', 1988],
        ['Calculus', 'James Stewart', 'Mathematics', 'Cengage', 2015],
        ['Linear Algebra Done Right', 'Sheldon Axler', 'Mathematics', 'Springer', 2015],
        ['Fundamentals of Physics', 'David Halliday', 'Physics', 'Wiley', 2013],
        ['Organic Chemistry', 'Paula Bruice', 'Chemistry', 'Pearson', 2016],
        ['Campbell Biology', 'Lisa Urry', 'Biology', 'Pearson', 2016],
        ['The Art of Computer Programming', 'Donald Knuth', 'Computer Science', 'Addison-Wesley', 1968],
        ['Design Patterns', 'Erich Gamma', 'Programming', 'Addison-Wesley', 1994],
        ['Thinking, Fast and Slow', 'Daniel Kahneman', 'Psychology', 'Farrar, Straus and Giroux', 2011],
        ['The Wealth of Nations', 'Adam Smith', 'Economics', 'W. Strahan', 1776],
        ['Freakonomics', 'Steven Levitt', 'Economics', 'William Morrow', 2005],
        ['The Story of My Experiments with Truth', 'M. K. Gandhi', 'Biography', 'Navajivan', 1927],
        ['Harry Potter and the Sorcerer\'s Stone', 'J. K. Rowling', 'Fiction', 'Bloomsbury', 1997],
        ['The Hobbit', 'J. R. R. Tolkien', 'Fiction', 'Allen & Unwin', 1937],
        ['Atomic Habits', 'James Clear', 'Self-Help', 'Avery', 2018],
    ];

    public function run(): void
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();

        $ay = DB::table('academic_years')->where('is_current', 1)->first()
            ?? DB::table('academic_years')->orderBy('id')->first();
        $ayId = $ay?->id;

        // Demo reset
        DB::table('book_issues')->delete();
        DB::table('books')->delete();

        $branches = DB::table('branches')->whereNull('deleted_at')->orderBy('id')->get();
        $count = max(1, $branches->count());
        $remainder = 100 % $count;

        $totalBooks = 0;
        $totalIssues = 0;

        foreach ($branches->values() as $bi => $branch) {
            $target = intdiv(100, $count) + ($bi < $remainder ? 1 : 0);

            $students = DB::table('users')->where('role', 'Student')->where('branch_id', $branch->id)->pluck('id')->all();
            $teachers = DB::table('users')->where('role', 'Teacher')->where('branch_id', $branch->id)->pluck('id')->all();

            for ($n = 1; $n <= $target; $n++) {
                $c = self::CATALOG[($bi * 7 + $n) % count(self::CATALOG)];
                $total = rand(2, 6);

                $bookId = DB::table('books')->insertGetId([
                    'branch_id' => $branch->id,
                    'school_id' => $branch->school_id,
                    'title' => $c[0],
                    'author' => $c[1],
                    'category' => $c[2],
                    'publisher' => $c[3],
                    'published_year' => $c[4],
                    'language' => 'English',
                    'isbn' => 'ISBN-' . $branch->id . '-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                    'total_copies' => $total,
                    'available_copies' => $total,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $totalBooks++;

                // ~60% of books get borrowing history
                if ((empty($students) && empty($teachers)) || rand(1, 100) > 60) {
                    continue;
                }

                $active = 0;
                $numIssues = rand(1, 3);
                for ($k = 0; $k < $numIssues; $k++) {
                    $useTeacher = !empty($teachers) && (empty($students) || rand(0, 1) === 1);
                    $pool = $useTeacher ? $teachers : $students;
                    if (empty($pool)) {
                        continue;
                    }
                    $memberId = $pool[array_rand($pool)];
                    $type = $useTeacher ? 'Teacher' : 'Student';
                    $loanDays = $useTeacher ? 30 : 14;

                    // 1 = returned, 2 = active, 3 = overdue (both active kinds keep status 'Issued')
                    $scenario = rand(1, 3);
                    $row = [
                        'book_id' => $bookId,
                        'branch_id' => $branch->id,
                        'school_id' => $branch->school_id,
                        'academic_year_id' => $ayId,
                        'student_id' => $useTeacher ? null : $memberId,
                        'teacher_id' => $useTeacher ? $memberId : null,
                        'borrower_type' => $type,
                        // fine_amount intentionally omitted here (DB default 0); the returned
                        // scenario sets it below. Using += would keep a base 0, so don't set it here.
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if ($scenario === 1) {
                        $issue = $today->copy()->subDays(rand(30, 90));
                        $due = $issue->copy()->addDays($loanDays);
                        $return = $due->copy()->addDays(rand(-5, 12));
                        $fine = $return->gt($due) ? $due->diffInDays($return) * 5 : 0;
                        $row += [
                            'issue_date' => $issue->toDateString(),
                            'due_date' => $due->toDateString(),
                            'return_date' => $return->toDateString(),
                            'status' => 'Returned',
                            'fine_amount' => $fine,
                        ];
                    } elseif ($scenario === 2 && $active < $total) {
                        $issue = $today->copy()->subDays(rand(1, max(1, $loanDays - 1)));
                        $due = $issue->copy()->addDays($loanDays);
                        $row += [
                            'issue_date' => $issue->toDateString(),
                            'due_date' => $due->toDateString(),
                            'return_date' => null,
                            'status' => 'Issued',
                        ];
                        $active++;
                    } elseif ($active < $total) {
                        $issue = $today->copy()->subDays($loanDays + rand(3, 25));
                        $due = $issue->copy()->addDays($loanDays); // in the past -> overdue
                        $row += [
                            'issue_date' => $issue->toDateString(),
                            'due_date' => $due->toDateString(),
                            'return_date' => null,
                            'status' => 'Issued',
                        ];
                        $active++;
                    } else {
                        continue; // no free copies for another active loan
                    }

                    DB::table('book_issues')->insert($row);
                    $totalIssues++;
                }

                if ($active > 0) {
                    DB::table('books')->where('id', $bookId)->update(['available_copies' => max(0, $total - $active)]);
                }
            }
        }

        $this->command->info("📚 Library demo data: {$totalBooks} books, {$totalIssues} issues across {$count} branches (AY id {$ayId}).");
    }
}
