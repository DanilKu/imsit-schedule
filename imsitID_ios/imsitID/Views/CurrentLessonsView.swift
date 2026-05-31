//
//  CurrentLessonsView.swift
//  imsitID
//

import SwiftUI

struct CurrentLessonsView: View {
    @EnvironmentObject var appState: AppState
    
    var body: some View {
        VStack(spacing: 14) {
            if let currentLesson = appState.currentLesson {
                CurrentLessonCard(lesson: currentLesson)
                    .transition(.asymmetric(
                        insertion: .move(edge: .top).combined(with: .opacity),
                        removal: .move(edge: .bottom).combined(with: .opacity)
                    ))
            }
            
            if let nextLesson = appState.nextLesson {
                NextLessonCard(lesson: nextLesson)
                    .transition(.asymmetric(
                        insertion: .move(edge: .top).combined(with: .opacity),
                        removal: .move(edge: .bottom).combined(with: .opacity)
                    ))
            }
        }
    }
}

struct CurrentLessonCard: View {
    let lesson: Lesson
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack {
                HStack(spacing: 8) {
                    Circle()
                        .fill(Color(red: 0.063, green: 0.725, blue: 0.506))
                        .frame(width: 10, height: 10)
                        .shadow(color: Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.6), radius: 4)
                    
                    Text("Сейчас")
                        .font(.system(size: 14, weight: .bold))
                        .foregroundColor(Color(red: 0.063, green: 0.725, blue: 0.506))
                }
                .padding(.horizontal, 14)
                .padding(.vertical, 8)
                .background(
                    LinearGradient(
                        colors: [
                            Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.25),
                            Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.15)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .cornerRadius(14)
                .overlay(
                    RoundedRectangle(cornerRadius: 14)
                        .stroke(
                            LinearGradient(
                                colors: [
                                    Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.4),
                                    Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.2)
                                ],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            ),
                            lineWidth: 1.5
                        )
                )
                
                Spacer()
                
                Text(lesson.timeRange)
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.white.opacity(0.9))
            }
            
            Text(lesson.subjectName)
                .font(.system(size: 20, weight: .bold))
                .foregroundColor(.white)
                .lineLimit(2)
                .fixedSize(horizontal: false, vertical: true)
            
            HStack(spacing: 12) {
                Label(lesson.roomNumber, systemImage: "door.left.hand.open")
                    .font(.system(size: 14, weight: .medium))
                    .foregroundColor(.white.opacity(0.9))
                
                Text("•")
                    .foregroundColor(.white.opacity(0.4))
                    .font(.system(size: 14))
                
                if let groups = lesson.groups, !groups.isEmpty {
                    Label(groups.joined(separator: ", "), systemImage: "person.3.fill")
                        .font(.system(size: 14, weight: .medium))
                        .foregroundColor(.white.opacity(0.9))
                        .lineLimit(1)
                } else {
                    Label(lesson.teacherName, systemImage: "person.fill")
                        .font(.system(size: 14, weight: .medium))
                        .foregroundColor(.white.opacity(0.9))
                        .lineLimit(1)
                }
            }
            
            VStack(spacing: 8) {
                GeometryReader { geometry in
                    ZStack(alignment: .leading) {
                        RoundedRectangle(cornerRadius: 8)
                            .fill(Color.white.opacity(0.15))
                            .frame(height: 8)
                        
                        RoundedRectangle(cornerRadius: 8)
                            .fill(
                                LinearGradient(
                                    colors: [
                                        Color(red: 0.063, green: 0.725, blue: 0.506),
                                        Color(red: 0.022, green: 0.588, blue: 0.412)
                                    ],
                                    startPoint: .leading,
                                    endPoint: .trailing
                                )
                            )
                            .frame(width: geometry.size.width * lesson.progress, height: 8)
                            .shadow(color: Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.5), radius: 4)
                    }
                }
                .frame(height: 8)
                
                HStack {
                    Label("до конца пары: \(lesson.remainingTime)", systemImage: "hourglass")
                        .font(.system(size: 13, weight: .medium))
                        .foregroundColor(.white.opacity(0.7))
                }
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(20)
        .background(
            LinearGradient(
                colors: [
                    Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.15),
                    Color.white.opacity(0.08)
                ],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        )
        .cornerRadius(20)
        .overlay(
            RoundedRectangle(cornerRadius: 20)
                .stroke(
                    LinearGradient(
                        colors: [
                            Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.4),
                            Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.1)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    ),
                    lineWidth: 1.5
                )
        )
        .shadow(color: Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.2), radius: 16, x: 0, y: 8)
    }
}

struct NextLessonCard: View {
    let lesson: Lesson
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack {
                HStack(spacing: 8) {
                    Image(systemName: "arrow.right.circle.fill")
                        .font(.system(size: 12))
                        .foregroundColor(Color(red: 0.231, green: 0.510, blue: 0.965))
                    
                    Text("Следующая")
                        .font(.system(size: 14, weight: .bold))
                        .foregroundColor(Color(red: 0.231, green: 0.510, blue: 0.965))
                }
                .padding(.horizontal, 14)
                .padding(.vertical, 8)
                .background(
                    LinearGradient(
                        colors: [
                            Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.25),
                            Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.15)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .cornerRadius(14)
                .overlay(
                    RoundedRectangle(cornerRadius: 14)
                        .stroke(
                            LinearGradient(
                                colors: [
                                    Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.4),
                                    Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.2)
                                ],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            ),
                            lineWidth: 1.5
                        )
                )
                
                Spacer()
                
                Text(lesson.timeRange)
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.white.opacity(0.9))
            }
            
            Text(lesson.subjectName)
                .font(.system(size: 20, weight: .bold))
                .foregroundColor(.white)
                .lineLimit(2)
                .fixedSize(horizontal: false, vertical: true)
            
            HStack(spacing: 12) {
                Label(lesson.roomNumber, systemImage: "door.left.hand.open")
                    .font(.system(size: 14, weight: .medium))
                    .foregroundColor(.white.opacity(0.9))
                
                Text("•")
                    .foregroundColor(.white.opacity(0.4))
                    .font(.system(size: 14))
                
                if let groups = lesson.groups, !groups.isEmpty {
                    Label(groups.joined(separator: ", "), systemImage: "person.3.fill")
                        .font(.system(size: 14, weight: .medium))
                        .foregroundColor(.white.opacity(0.9))
                        .lineLimit(1)
                } else {
                    Label(lesson.teacherName, systemImage: "person.fill")
                        .font(.system(size: 14, weight: .medium))
                        .foregroundColor(.white.opacity(0.9))
                        .lineLimit(1)
                }
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(20)
        .background(
            LinearGradient(
                colors: [
                    Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.15),
                    Color.white.opacity(0.08)
                ],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        )
        .cornerRadius(20)
        .overlay(
            RoundedRectangle(cornerRadius: 20)
                .stroke(
                    LinearGradient(
                        colors: [
                            Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.4),
                            Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.1)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    ),
                    lineWidth: 1.5
                )
        )
        .shadow(color: Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.2), radius: 16, x: 0, y: 8)
    }
}

